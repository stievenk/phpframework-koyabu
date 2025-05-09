<?php
namespace Koyabu\Webapi;

class SSLEncrypt {

    function __construct(private $FIRSTKEY = '', private $SECONDKEY = '') {
        $this->FIRSTKEY = $this->FIRSTKEY ? $this->FIRSTKEY : 'MyFirst';
        $this->SECONDKEY = $this->SECONDKEY ? $this->SECONDKEY : 'MySecond';
    }

    public function data_encode($data) {
        $first_key = $this->FIRSTKEY;
        $second_key = $this->SECONDKEY;
            
        $method = "aes-256-cbc";    
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
                
        $first_encrypted = openssl_encrypt($data,$method,$first_key, OPENSSL_RAW_DATA ,$iv);    
        $second_encrypted = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
                    
        $output = base64_encode($iv.$second_encrypted.$first_encrypted);    
        return $output;
    }

    public function data_decode($input)
    {
        $first_key = $this->FIRSTKEY;
        $second_key = $this->SECONDKEY;           
        $mix = base64_decode($input);
                
        $method = "aes-256-cbc";    
        $iv_length = openssl_cipher_iv_length($method);
                    
        $iv = substr($mix,0,$iv_length);
        $second_encrypted = substr($mix,$iv_length,64);
        $first_encrypted = substr($mix,$iv_length+64);
                    
        $data = openssl_decrypt($first_encrypted,$method,$first_key,OPENSSL_RAW_DATA,$iv);
        $second_encrypted_new = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);
        
    //    return $data;
        if (hash_equals($second_encrypted,$second_encrypted_new))
        return $data;
            
        return false;
    }

    function __destruct() {

    }
}

?>