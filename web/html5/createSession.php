<?php
////error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
error_reporting(1);
include '../pccConfig.php';
include '../utils.php';

//--------------------------------------------------------------------
//
//  For this sample, the location to look for source documents
//  specified only by name and the PCC Imaging Services (PCCIS) URL
//  are configured in PCC.config.
//
//--------------------------------------------------------------------
header('Content-Type: application/json');

PccConfig::parse("../pcc.config");

$viewingSessionId = "";
$document = null;

$documentQueryParameter = stripslashes($_GET['document']);
if (!empty($documentQueryParameter)) {

    if (strstr($documentQueryParameter, "http://") || strstr($documentQueryParameter, "https://")) {
        $document = $documentQueryParameter;
    } else {
        $filename = basename($documentQueryParameter);
        $folder = dirname($documentQueryParameter);
        if ($folder == ".") {
            $folder = PccConfig::getDocumentsPath();
        } else {
            $folder = $folder . "/";
        }

        $document = Utils::combine($folder, $filename);
    }

    $extension = pathinfo($document, PATHINFO_EXTENSION);

    $correctPath = PccConfig::isFileSafeToOpen($document);
    if (!$correctPath) {
        header('HTTP/1.0 403 Forbidden');
        echo('<h1>403 Forbidden</h1>');
        return;
    }

    $acsApiKey = PccConfig::getApiKey();

    // Set viewing session properties using JSON.
    $data = array(
        // Store some information in PCCIS to be retrieved later.
        'externalId' => Utils::getHashString($document),
        'tenantId' => 'My User ID',
        // The following are examples of arbitrary information as key-value
        // pairs that PCCIS will associate with this document request.
        'origin' => array(
            'ipAddress' => $_SERVER['REMOTE_ADDR'],
            'hostName' => $_SERVER['REMOTE_HOST'],
            'sourceDocument' => $document),
        // Specify rendering properties.
        'render' => array(
            'flash' => array(
                'optimizationLevel' => 1),
            'html5' => array(
                'alwaysUseRaster' => false))
    );

    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n" .
            "Acs-Api-Key: $acsApiKey\r\n" .
            "Accusoft-Affinity-Hint: $document\r\n",
            'content' => json_encode($data),
        ),
    );

    // Request a new viewing session from PCCIS.
    //   POST http://localhost:18681/PCCIS/V1/ViewingSession
    //
    $url = PccConfig::getImagingService() . "/ViewingSession";
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);

    // Store the ID for this viewing session that is returned by PCCIS.
    $viewingSessionId = $response->viewingSessionId;


    if (file_exists($document)) {
        // Open document to upload.
        $fileHandle = fopen($document, "rb");
        $fileContents = stream_get_contents($fileHandle);
        fclose($fileHandle);

        $options = array(
            'http' => array(
                'method' => 'PUT',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Acs-Api-Key: $acsApiKey\r\n",
                'content' => $fileContents,
            ),
        );

        // Upload File to PCCIS.
        //   PUT http://localhost:18681/PCCIS/V1/ViewingSessions/u{Viewing Session ID}/SourceFile?FileExtension={File Extension}
        // Note the "u" prefixed to the Viewing Session ID. This is required when providing
        //   an unencoded Viewing Session ID, which is what PCCIS returns from the initial POST.
        //
        $url = PccConfig::getImagingService() . "/ViewingSession/u$viewingSessionId/SourceFile?FileExtension=$extension";
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);

        $data = array(
            'viewer' => 'HTML5'
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Acs-Api-Key: $acsApiKey\r\n",
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'content' => json_encode($data),
            ),
        );

        // Start Viewing Session in PCCIS.
        //   POST http://localhost:18681/PCCIS/V1/ViewingSessions/u{Viewing Session ID}/Notification/SessionStarted
        //
        $url = PccConfig::getImagingService() . "/ViewingSession/u$viewingSessionId/Notification/SessionStarted";
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    } else {
        $url = PccConfig::getImagingService() . "/ViewingSession/u$viewingSessionId/Notification/SessionStopped";

        $data = array(
            'endUserMessage' => "Document not found: $documentQueryParameter",
            'httpStatus' => 504
        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Acs-Api-Key: $acsApiKey\r\n",
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'content' => json_encode($data),
            ),
        );

        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }

    $data = array('viewingSessionId' => $viewingSessionId);
    echo json_encode($data);
}
else {
    $data = array('error' => 'document parameter is required');
    echo json_encode($data);
}
?>
