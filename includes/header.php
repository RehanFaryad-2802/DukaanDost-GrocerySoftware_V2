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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
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
        color: rgba(255,255,255,.8);
        padding: 10px 15px;
        margin: 2px 0;
    }
    .sidebar .nav-link:hover {
        color: #fff;
        background-color: rgba(255,255,255,.1);
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
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
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
</style>
</head>
<body>