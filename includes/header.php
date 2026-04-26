<?php
require_once 'config/database.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['store_name'] ?? 'Grocery Billing System'; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <!-- <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet"> -->
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="assets/site.webmanifest">
    <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (local) -->
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            width: 16.666667%;
            overflow-y: auto !important;
            max-height: 100vh;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, .8);
            padding: 10px 15px;
            margin: 2px 0;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
        }

        main {
            padding-top: 20px;
            margin-left: 16.666667%;
        }

        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        /* Scrollbar styling */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #343a40;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #6c757d;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #adb5bd;
        }

        /* Quick add section styling */
        #quick_add_section {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
            margin-top: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }

        #quick_add_section .alert {
            margin-bottom: 10px;
            padding: 8px 12px;
        }

        #quick_add_section input,
        #quick_add_section select {
            font-size: 14px;
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 5px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* .urdu-text, */
        /* *,
        [dir="rtl"] {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Alvi Nastaleeq', serif;
        } */
    </style>
</head>

<body>