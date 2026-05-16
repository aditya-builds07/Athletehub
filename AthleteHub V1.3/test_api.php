<?php session_start(); $_SESSION["user_id"] = 1; $_GET["q"] = "a"; $_GET["role"] = "all"; require "api/search_users.php";
