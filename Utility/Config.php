<?php

// nagad credentials
$nagad_callback_url = 'http://127.0.0.1/nagad/verify.php';
const NAGAD_MERCHANT_ID = '';
const NAGAD_MERCHANT_NUMBER = '';
const NAGAD_MODE = 'sandbox';
const NAGAD_PUBLIC_KEY = "";
const NAGAD_PRIVATE_KEY = "";


// db connect
$connection = mysqli_connect('127.0.0.1', 'root', '', 'nagad');

// check db status
if ($connection == null) {
    die('Connection failed.');
}