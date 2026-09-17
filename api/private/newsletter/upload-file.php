<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_file_upload.php';
handleAdminFileUpload(
  'fichiers_news',
  $_POST['slug'] ?? ''
);
