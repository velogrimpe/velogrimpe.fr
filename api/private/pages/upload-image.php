<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_image_upload.php';
handleAdminImageUpload(
  'images_pages',
  $_POST['slug'] ?? ''
);
