<?php
/**
*----------------------------------------------------------------------
*<copyright file="utils.php" company="Accusoft Corporation">
*CopyrightÂ© 1996-2014 Accusoft Corporation.  All rights reserved.
*</copyright>
*----------------------------------------------------------------------
*/
class Utils {

    function encodeUrl($input) {
        if (empty($input)) {
            return "";
        }

        $input_array = unpack('C*', $input);
        $input_array_length = count($input_array);
        $b = array_pad(array(), $input_array_length * 2, 0);

        for ($i = 0; $i < count($input_array); $i++) {
            $b[($i * 2)] = (int) ($input_array[($i + 1)] % 256);
            $b[(($i * 2) + 1)] = (int) ($input_array[($i + 1)] / 256);
        }

        $output_string = implode(array_map("chr", $b));
        $output_string = base64_encode($output_string);

        $counter = 0;
        $done = false;
        do {
            $pos = strrpos($output_string, "=");
            if ($pos === false) {
                $output_string .= $counter;
                $done = true;
            } else {
                $output_string = substr($output_string, 0, $pos);
                $counter++;
            }
        } while (!$done);

        return urlencode($output_string);
    }

    function decodeParam($arg) {
        $arg = preg_replace('/^e/', '', $arg);
        $arg = preg_replace('/1$/', '', $arg);
        $arg = preg_replace('/2$/', '', $arg);
        $arg = preg_replace('/3$/', '', $arg);
        $arg = base64_decode($arg);
        $arg = preg_replace('/\x0/', '', $arg);
        return $arg;
    }

    function xmlToJson($xmlString){
        $simpleXml = simplexml_load_string($xmlString);
        $json = json_encode($simpleXml);
        return $json;
    }

    /**
     * generates a hash of the document's location value. used for
     * associating annotation files to their original source documents.
     * @return string the hash string.
     */
    function getHashString($input) {
        return sha1($input);
    }

    function combine($folder, $file) {
        if (!Utils::endsWith($folder, "/")) {
            $folder = $folder . "/";
        }

        return $folder . $file;
    }

    private function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    function url_exists($url) {
        $file_headers = @get_headers($url);

        // look for this response: HTTP/1.0 404 Not Found'
        return ( strpos ($file_headers[0], '404') !== false) ? false : true;
    }

}

?>
