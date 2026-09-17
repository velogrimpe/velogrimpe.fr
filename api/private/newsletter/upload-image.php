<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_image_upload.php';
handleAdminImageUpload(
  'bdd/images_news',
  $_POST['slug'] ?? ''
);
