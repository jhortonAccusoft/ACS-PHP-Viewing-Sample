<?php

/**
*----------------------------------------------------------------------
*<copyright file="pccConfig.php" company="Accusoft Corporation">
*CopyrightÂ© 1996-2014 Accusoft Corporation.  All rights reserved.
*</copyright>
*----------------------------------------------------------------------
*/

/**
 *  Obtains information from a configuration file (i.e."pcc.config")
 */
class PccConfig {

    public static $apiKey = "";
    public static $documentPath = "";
    public static $webServiceHost = "";
    public static $webServicePort = "";
    public static $webServiceScheme = "";
    public static $webServicePath = "";
    public static $webServiceUrl = "";
    public static $markupsPath = "";
    public static $imageStampPath = "";
    public static $validImageStampTypes = "";
    public static $enableDocumentPath = false;
    private static $parent_tag_name;
    private static $child_tag_name;

    /**
     * replace %VARIABLES% with their values
     */
    static function inlineEnvVariables($str) {
        preg_match_all("/\\%([A-Za-z]*)\\%/", $str, $matches, PREG_OFFSET_CAPTURE);

        $ret = $str;
        for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
            $varname = $matches[1][$i][0];
            $varValue = getenv($varname);
            if ($varValue != null) {
                $ret = substr($ret, 0, $matches[0][$i][1]) .
                        $varValue .
                        substr($ret, $matches[0][$i][1] + strlen($matches[0][$i][0]));
            }
        }

        return $ret;
    }

    /**
     * handles XML element starting
     */
    public static function tagStart($parser, $name, $attrs) {
        if (is_null(PccConfig::$parent_tag_name)) {
            PccConfig::$parent_tag_name = $name;
        } else {
            if ($name == 'DocumentPath' || $name == 'WebServicePort' ||
                    $name == 'WebServiceHost' || $name == 'WebServiceScheme' || $name == 'WebServicePath' ||
                    $name == 'MarkupsPath' || $name == 'ImageStampPath' || 'ValidImageStampTypes' ||
                    $name == 'EnableDocumentPath' || $name = 'ApiKey'
            )
                PccConfig::$child_tag_name = $name;
        }
    }

    /**
     * handles XML element stop
     */
    public static function tagEnd($parser, $name) {
        if ($name == 'DocumentPath' || $name == 'WebServicePort' ||
                $name == 'WebServiceHost' || $name == 'WebServiceScheme' || $name == 'WebServicePath' ||
                $name == 'MarkupsPath' || $name == 'ImageStampPath' || 'ValidImageStampTypes' ||
                $name == 'EnableDocumentPath' || $name = 'ApiKey'
        )
            PccConfig::$child_tag_name = null;
    }

    /**
     * handles XML data
     */
    public static function tagContent($parser, $data) {
        if (PccConfig::$parent_tag_name == 'Config') {
            if (PccConfig::$child_tag_name == "ApiKey")
                PccConfig::$apiKey = $data;
            if (PccConfig::$child_tag_name == "DocumentPath")
                PccConfig::$documentPath = $data;
            if (PccConfig::$child_tag_name == "WebServiceHost")
                PccConfig::$webServiceHost = $data;
            if (PccConfig::$child_tag_name == "WebServicePort")
                PccConfig::$webServicePort = $data;
            if (PccConfig::$child_tag_name == "WebServicePath")
                PccConfig::$webServicePath = $data;
            if (PccConfig::$child_tag_name == "WebServiceScheme")
                PccConfig::$webServiceScheme = $data;
            if (PccConfig::$child_tag_name == "MarkupsPath")
                PccConfig::$markupsPath = $data;
            if (PccConfig::$child_tag_name == "ImageStampPath")
                PccConfig::$imageStampPath = $data;
            if (PccConfig::$child_tag_name == "ValidImageStampTypes")
                PccConfig::$validImageStampTypes = $data;
            if (PccConfig::$child_tag_name == "EnableDocumentPath") {
                if (trim(strtolower($data)) == 'false') {
                    PccConfig::$enableDocumentPath = false;
                } else {
                    PccConfig::$enableDocumentPath = (bool) trim(strtolower($data));
                }
            }
        }
    }

    /**
     * improves path appearance
     */
    static function processPath($path, $curPath) {
        $curPath = str_replace("\\", "/", $curPath);
        if (!(strrpos($curPath, "/") === (strlen($curPath) - 1)))
            $curPath = $curPath . "/";
        if ($path == null)
            return null;
        $path = PccConfig::inlineEnvVariables($path);
        $path = str_replace("\\", "/", $path);
        if (strpos($path, "./") === 0)
            $path = $curPath . substr($path, 2);
        if (!(strrpos($path, "/") === (strlen($path) - 1)))
            $path = $path . "/";
        return $path;
    }

    /**
     * parses the pcc.config file and stores the contents
     * @param string $config_path path or name of config file
     */
    public static function parse($config_path) {
        $parser = xml_parser_create();

        //xml_set_object($parser, $this);
        xml_set_element_handler($parser, array(PccConfig, 'tagStart'), array(PccConfig, 'tagEnd'));
        xml_set_character_data_handler($parser, array(PccConfig, 'tagContent'));
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        $xml = file_get_contents($config_path);

        if (!xml_parse($parser, str_replace(array("\n", "\r", "\t"), '', $xml))) {
            echo xml_error_string(xml_get_error_code($parser));
        }

        PccConfig::$documentPath = PccConfig::processPath(PccConfig::$documentPath, realpath(dirname(__FILE__)));
        PccConfig::$markupsPath = PccConfig::processPath(PccConfig::$markupsPath, realpath(dirname(__FILE__)));
        PccConfig::$imageStampPath = PccConfig::processPath(PccConfig::$imageStampPath, realpath(dirname(__FILE__)));
        PccConfig::$webServiceUrl = PccConfig::$webServiceScheme . '://' . PccConfig::$webServiceHost . ':' . PccConfig::$webServicePort . '/' . PccConfig::$webServicePath;
    }

    /**
     * gets the API key
     * @return string
     */
    public static function getApiKey() {
        return PccConfig::$apiKey;
    }

    /**
     * gets the $path for where the document folder resides
     * @return string
     */
    public static function getDocumentsPath() {
        return PccConfig::$documentPath;
    }

    /**
     * gets the $path for where the annotation files resides
     * @return string
     */
    public static function getMarkupsPath() {
        return PccConfig::$markupsPath;
    }

    /**
     * gets the $path for where the image stamps files resides
     * @return string
     */
    public static function getImageStampPath() {
        return PccConfig::$imageStampPath;
    }

    /**
     * gets the acceptable formats to be included as image stamps
     * @return string
     */
    public static function getValidImageStampTypes() {
        return PccConfig::$validImageStampTypes;
    }

    /**
     * gets the URL for the imaging services (PCCIS)
     * @return string
     */
    public static function getImagingService() {
        return PccConfig::$webServiceUrl;
    }

    /**
     * if enabled, checks if the local file is being opened from the configured
     * Documents path or not
     * @param string $origPath
     * @return boolean
     * @see PccConfig::$enableDocumentPath
     */
    public static function isFileSafeToOpen($origPath) {
        if (PccConfig::$enableDocumentPath == false) {
            return true;
        }
        $realPath = realpath($origPath);

        if ($realPath == false) {
            return false;
        }

        return PccConfig::$isFolderSafeToOpen(dirname($realPath));
    }

    public static function isFolderSafeToOpen($origPath) {
        if (PccConfig::$enableDocumentPath == false) {
            return true;
        }
        $fullPath = realpath($origPath);
        $docPath = realpath(PccConfig::$documentPath);
        if ($fullPath === false || $docPath === false) {
            return false;
        }
        if (startsWith($fullPath, $docPath)) {
            return true;
        }

        $markupsPath = dirname(realpath(PccConfig::$markupsPath));

        if (startsWith($fullPath, $markupsPath)) {
            return true;
        }

        $imageStampPath = dirname(realpath(PccConfig::$imageStampPath));

        if (startsWith($fullPath, $imageStampPath)) {
            return true;
        }

        return false;
    }

}

?>
