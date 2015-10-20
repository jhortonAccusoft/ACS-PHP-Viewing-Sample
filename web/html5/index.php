<?php
////error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
error_reporting(0);
include '../pccConfig.php';
include '../utils.php';

//--------------------------------------------------------------------
//
//  For this sample, the location to look for source documents
//  specified only by name and the PCC Imaging Services (PCCIS) URL
//  are configured in PCC.config.
//
//--------------------------------------------------------------------

PccConfig::parse("../pcc.config");

$viewingSessionId = "";
$document = null;
$languageFilePath = "../language.json";
$predefinedSearchJSONPath = "../predefinedSearch.json";
$redactionReasonsJSONPath = "../redactionReason.json";

// Check for a language.json file, and load it if one exists. This is
// used by the HTML5 viewer to allow user customization of the various
// text strings it uses for buttons, tooltips and menu items.
$languageElements = "{}";
if (file_exists($languageFilePath)) {
    $languageElements = file_get_contents($languageFilePath);
}

// Check for a predefinedSearch.json file, and load it if one exists. This is
// used by the HTML5 viewer to allow user customization of the various
// predefined search options.
$predefinedSearch = "{}";
if (file_exists($predefinedSearchJSONPath)) {
    $predefinedSearch = file_get_contents($predefinedSearchJSONPath);
}

// Check for a redactionReason.json file, and load it if one exists. This is
// used by the HTML5 viewer to allow user to add text explanations to
// redacted areas.
$redactionReasons = "{}";
if (file_exists($redactionReasonsJSONPath)) {
    $redactionReasons = file_get_contents($redactionReasonsJSONPath);
}

// Scan this directory and add any files with 'Template.html' in the file name to
// to a templates array. The files will be serialized before being added to the array.
$htmlFiles = glob("*.[hH][tT][mM][lL]");
$templateFiles = preg_grep('/\w*(template\.html)$/i', $htmlFiles);

foreach ($templateFiles as $filename) {

    $tplName = str_ireplace('template.html', '', $filename);
    $tplFile = preg_replace("/\s+/", " ", file_get_contents($filename));
    $tpls[$tplName] = $tplFile;
}

$documentQueryParameter = stripslashes($_GET['document']);
$originalDocumentName = $documentQueryParameter;

if (!empty($documentQueryParameter)) {

    if (strstr($documentQueryParameter, "http://") || strstr($documentQueryParameter, "https://")) {
        $document = $documentQueryParameter;
        $originalDocumentName = $documentQueryParameter;
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

    if ( (strstr($documentQueryParameter, "http://") || strstr($document, "https://")) && Utils::url_exists($document) ||
            file_exists($document)) {
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
} else {

    // If there was no 'document' parameter, but a 'viewingSessionId'
    // value exists, there is viewing session already so we don't
    // need to do anything else. This case is true when viewing attachments
    // of email message document types (.EML and .MSG).

    $viewingSessionId = stripslashes($_GET['viewingSessionId']);
    if (!empty($viewingSessionId)) {

        // Request properties about the viewing session from PCCIS.
        // The properties will include an identifier of the source document
        // from which the attachment was obtained. The name of the attachment
        // is also available. These values are used to just to provide
        // contextual information to the user.
        //   GET http://localhost:18681/PCCIS/V1/ViewingSession/u{Viewing Session ID}
        //
        $url = PccConfig::getImagingService() . "/ViewingSession/u" . urlencode($viewingSessionId);
        $result = file_get_contents($url);
        $response = json_decode($result);

        $document = $response->origin->sourceDocument . ":{" . $response->attachmentDisplayName . "}";
    } else {
        echo('You must include the name of a document in the URL.<br/>');
        $link = $_SERVER['PHP_SELF'] . '?document=sample.doc';
        echo('For example, click on this link: <a href="' . $link . '">' . $link . '</a>');
        return;
    }
}
?>
<!DOCTYPE html>
<html>
    <head id="Head1" runat="server">
        <meta charset="utf-8"/>
        <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
        <!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
        <title>PCC HTML5 PHP Sample</title>
        <link rel="icon" href="favicon.ico" type="image/x-icon"/>

        <link rel="stylesheet" href="css/normalize.min.css"/>
        <link rel="stylesheet" href="css/viewercontrol.css"/>
        <link rel="stylesheet" href="css/viewer.css"/>

        <script type="text/javascript">
            var PCCViewer = window.PCCViewer || {};
        </script>

        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <script>window.jQuery || document.write('<script src="js/jquery-1.10.2.min.js"><\/script>');</script>
        <script src="js/underscore.min.js"></script>
        <script src="js/jquery.hotkeys.min.js"></script>

        <!--[if lt IE 9]>
            <link rel="stylesheet" href="css/legacy.css">
            <script src="js/selectivizr.js"></script>
            <script src="js/html5shiv.js"></script>
        <![endif]-->

        <script src="js/viewercontrol.js"></script>
        <script src="js/viewer.js"></script>

    </head>
    <body>
        <script type="text/javascript">
            var viewingSessionId = '<?php echo urlencode($viewingSessionId); ?>';

            $(document).ready(function () {

                var pluginOptions = {
                    documentID: viewingSessionId,
                    imageHandlerUrl: "../pcc.php",
                    language: <?php echo $languageElements; ?>,
                    predefinedSearch: <?php echo $predefinedSearch ?>,
                    template: <?php echo json_encode($tpls) ?>,
                    signatureCategories: "Signature,Initials,Title",
                    immediateActionMenuMode: "hover",
                    redactionReasons: <?php echo $redactionReasons ?>,
                    documentDisplayName: "<?php echo $originalDocumentName ?>",
                    uiElements: {
                        download: true,
                        fullScreenOnInit: true,
                        advancedSearch: true
                    }
                };

                var viewerControl = $("#viewer1").pccViewer(pluginOptions).viewerControl;

                // The following javascript will process any attachments for the
                // email message document types (.EML and .MSG).
                setTimeout(requestAttachments, 500);

                var countOfAttachmentsRequests = 0;

                function receiveAttachments (data, textStatus, jqXHR) {

                    if (data == null || data.status != 'complete') {
                        // The request is not complete yet, try again after a short delay.
                        setTimeout(requestAttachments, countOfAttachmentsRequests * 1000);
                    }

                    if (data.attachments.length > 0) {
                        var links = '';
                        for (var i = 0; i < data.attachments.length; i++) {
                            var attachment = data.attachments[i];
                            links += '<a href="?viewingSessionId=' + attachment.viewingSessionId + '" target="blank">' + attachment.displayName + '</a><br/>';
                        }

                        $('#attachmentList').html(links);
                        $('#attachments').show();
                    }
                }

                function requestAttachments () {
                    if (countOfAttachmentsRequests < 10) {
                        countOfAttachmentsRequests++;
                        $.ajax('../pcc.php/ViewingSession/u' + viewingSessionId + '/Attachments', {dataType: 'json'}).done(receiveAttachments).fail(requestAttachments);
                    }
                }
            });

        </script>

        <div id="viewer1"></div>

        <div id="attachments" style="display:none;">
            <b>Attachments:</b>
            <p id="attachmentList">
            </p>
        </div>

    </body>
</html>
