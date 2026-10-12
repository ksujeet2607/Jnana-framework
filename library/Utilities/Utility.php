<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Library\Utilities;
use date;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Description of uitlity
 *
 * @author LENOVO
 */
trait Utility {

    /**
     *
     * @param string $mobile
     * @return string
     */
    public function obfuscate_mobile($mobile){
        $mob = explode("-", $mobile);
        $phone = $mob[1]!=''?$mob[1]:$mob[0];
        $ccode = $mob[1]!=''?$mob[0]:'';
        return $ccode.str_replace(range(0, 9), "*", substr($phone, 0, -4)) . substr($phone, -4);
    }

    /**
     *
     * @param string $val
     * @return
     */
    public function strip($val = "") {
        $val = addslashes($val);
        $val = str_replace("`", "", $val);
        $val = str_replace("~", "", $val);
        $val = str_replace("\r\n", " ", $val);
        $val = trim($val);
        $val = htmlspecialchars($val);
        return $val;
    }

    /**
     *
     * @param int $dif
     * @return date
     */
    public function getcdate($dif = 0) {

        if ($dif != "0") {
            return date("d-m-Y", strtotime("$dif days"));
        }
        return date("d-m-Y");
    }

    /**
     *
     * @param array $value
     * @return boolean
     */
    public function is_not_null($value) {

        if (is_array($value)) {
            if (count($value) > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            if (($value != '') && (strtolower($value) != 'null') && (strlen(trim($value)) > 0)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     *
     * @param array $array
     * @param string $value
     * @return boolean
     */
    public function is_empty($array, $value) {
        if ($this->is_not_null($array)) {
            $tmp_array = explode("~@~", $value);

            if (is_array($tmp_array)) {
                if (count($tmp_array) > 0) {
                    foreach ($tmp_array as $value) {
                        if (array_key_exists($value, $array)) {
                            if ($array[$value] == '') {
                                return false;
                            }
                        } else {
                            return false;
                        }
                    }
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     *
     * @param string $variable_type
     * @param string $variable_list
     * @return boolean
     */
    public function is_not_empty($variable_type = "", $variable_list = "") {

        $variables = explode("~@~", $variable_list);

        $error = 0;

        if ($variable_type == "POST") {

            foreach ($variables as $variable) {

                if (isset($_POST[$variable])) {

                    if (trim($_POST[$variable]) == "" && $_POST[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        } else if ($variable_type == "GET") {

            foreach ($variables as $variable) {

                if (isset($_GET[$variable])) {

                    if (trim($_GET[$variable]) == "" && $_GET[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        } else if ($variable_type == "FILES") {

            foreach ($variables as $variable) {

                if (!isset($_FILES[$variable])) {
                    $error++;
                }
            }
        } else if ($variable_type == "SESSION") {

            foreach ($variables as $variable) {

                if (isset($_SESSION[$variable])) {

                    if (trim($_SESSION[$variable]) == "" && $_SESSION[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        } else if ($variable_type == "REQUEST") {

            foreach ($variables as $variable) {

                if (isset($_REQUEST[$variable])) {

                    if (trim($_REQUEST[$variable]) == "" && $_REQUEST[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        } else if ($variable_type == "COOKIE") {

            foreach ($variables as $variable) {

                if (isset($_COOKIE[$variable])) {

                    if (trim($_COOKIE[$variable]) == "" && $_COOKIE[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        } else if ($variable_type == "SERVER") {

            foreach ($variables as $variable) {

                if (isset($_SERVER[$variable])) {

                    if (trim($_SERVER[$variable]) == "" && $_SERVER[$variable] == NULL) {
                        $error++;
                    }
                } else {
                    $error++;
                }
            }
        }

        if ($error == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param array $array
     * @param string $variable_list
     * @return boolean
     */
    public function isNotEmpty($array = array(), $variable_list = "") {

        $variables = explode("~@~", $variable_list);

        $error = 0;
        foreach ($variables as $variable) {

            if (isset($array[$variable])) {

                if (trim($array[$variable]) == "" || $array[$variable] == NULL) {
                    $error++;
                }
            } else {
                $error++;
            }
        }
        if ($error == 0) {
            return true; // No variable are empty
        } else {
            return false; // Yes variable are not empty
        }
    }

    /**
     *
     * @param string $date
     * @param string $format
     * @param boolean $datepicker
     * @return date
     */
    public function get_valid_date($date, $format = "d-m-Y", $datepicker = false) {
        if ($date == "" || $date == "0000-00-00" || $date == "0000-00-00 00:00:00" || $date == "1970-01-01" || $date == "1970-01-01 00:00:00") {
            if ($datepicker) {
                $response = date($format);
            } else {
                $response = "";
            }
        } else {
            $response = date($format, strtotime($date));
        }

        return $response;
    }

    public function get_ip(){
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
        {
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
        {
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $ip=$_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }



    public function encrypt($plainText,$key)
    {
        $key = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }

    public function decrypt($encryptedText,$key)
    {
        $key = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = $this->hextobin($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }

    public function hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString="";
        $count=0;
        while($count<$length)
        {
            $subString =substr($hexString,$count,2);
            $packedString = pack("H*",$subString);
            if ($count==0)
            {
                $binString=$packedString;
            }

            else
            {
                $binString.=$packedString;
            }

            $count+=2;
        }
        return $binString;
    }

    public function Flash_Message($msg, $type='success', $timer=1500) {
        $message = '<script>
                        $(document).ready(function(){
                            Swal.fire({
                                position: "top-end",
                                icon: "'.$type.'",
                                title: "'.$msg.'",
                                showConfirmButton: false,
                                timer: '.$timer.'
                              });
                        });
                    </script>';
        $_SESSION["flash"] = $message;
    }
    /*
     *
     */
    public function goback($step=-1): void{
        echo "<script>window.history.go($step)</script>";
        exit(0);
    }

    public function sort_array_asc($array){
        $result = [];
        foreach ($array as $value){
            $arr = $value;
            ksort($arr);
            $result[] = $arr;
        }
        return $result;
    }

    public function sort_array_desc($array){
        $result = [];
        foreach ($array as $value){
            $arr = $value;
            ksort($arr);
            $result[] = $arr;
        }
        return $result;
    }

    public function unlinkFile($path): bool{
        if(!$path){
            return false;
        }
        if(str_contains(base_uri(), $path)){
            $path = str_replace(base_uri(), '', $path);
        }
        if(str_contains("css/", $path)){
            $path = str_replace("css/", '', $path);
        }
        $path = public_path($path);
        if(file_exists($path)){
            unlink($path);
            return true;
        }
        return false;

    }

    public function checkFileExistsCurl($url) {
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_NOBODY, true); // We only need the headers
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Don't output the response
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification (use with caution)

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP response code
        curl_close($ch);

        return $httpCode == 200;
    }

    public function checkFileExists($url) {
        $headers = @get_headers($url);
        if ($headers && strpos($headers[0], '200') !== false) {
            return true;
        }
        return false;
    }

    public function amountToWords($number) {
        $no = floor($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = [];
        $words = [
            0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
            15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty',
            50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty',
            90 => 'ninety'
        ];
        $digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];

        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred :
                    $words[floor($number / 10) * 10] . " " . $words[$number % 10] .
                    " " . $digits[$counter] . $plural . " " . $hundred;
            } else $str[] = null;
        }

        $rupees = implode('', array_reverse($str));
        $paise = ($point) ? "and " . $words[floor($point / 10)] . " " . $words[$point % 10] . " paise" : '';
        return ($rupees ? $rupees . "rupees " : '') . $paise;
    }

    public function getRoundingOffDetails($amount) {
        // Get the rounded amount
        $roundedAmount = round($amount);

        // Calculate the rounding-off digit
        $roundingOffDigit = $roundedAmount - $amount;

        // Return details
        return [
            'original_amount' => $amount,
            'rounded_amount' => $roundedAmount,
            'rounding_off_digit' => round($roundingOffDigit, 2) // Ensures precision to 2 decimal places
        ];
    }

    public function generatePDFWithCSS($htmlContent, $cssStyles, $outputPath) {
        // Configure Dompdf options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Allow loading of external assets like images or CSS
        $dompdf = new Dompdf($options);

        // Combine HTML and CSS
        $html = "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                $cssStyles
            </style>
        </head>
        <body>
            $htmlContent
        </body>
        </html>";

        // Load HTML content into Dompdf
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'landscape');

        // Render the HTML as PDF
        $dompdf->render();

        // Save the PDF to a file
        file_put_contents($outputPath, $dompdf->output());
    }

    public function generateRandomString($length = 15) {
        $characters = '0123456789-abcdefghijklmnopqrstuvwxyz_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function toResourceName(string $controllerName): string
    {
        // Remove the "Controller" suffix if present
        $baseName = preg_replace('/Controller$/', '', $controllerName);

        // Convert from PascalCase to snake_case
        $snakeCaseName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $baseName));

        // Convert to kebab-case or another format if needed
        // $resourceName = str_replace('_', '-', $snakeCaseName);

        return $snakeCaseName;
    }

    public function getNormalizedClassName(string $fqcn): string
    {
        // Get the short class name (remove namespace)
        $parts = explode('\\', $fqcn);
        $shortName = end($parts);

        // Remove "Controller" suffix if it exists
        $normalized = preg_replace('/Controller$/', '', $shortName);

        // Convert to lowercase
        return strtolower($normalized);
    }

}
