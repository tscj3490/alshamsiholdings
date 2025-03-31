To deploy the current local version into server, you have to make the following changes.
- index.php
  Change $assign_to_config['cp_url'] = 'http://localhost/alshamsiholdings/admin.php'; to $assign_to_config['cp_url'] = 'http://alshamsiholdings.com/admin.php';
  Change $assign_to_config['site_url'] = 'http://localhost/alshamsiholdings'; to $assign_to_config['site_url'] = 'http://alshamsiholdings.com/';
- system/expressionengine/config/config.php
  Change $config['cp_url'] = "http://localhost/alshamsiholdings/system/index.php"; to $config['cp_url'] = "http://alshamsiholdings.com/system/index.php";
- In database, exp_upload_prefs table
  url of id 1: http://localhost/alshamsiholdings/uploads/images/ to http://alshamsiholdings.com/uploads/images/
  url of id 2: http://localhost/alshamsiholdings/uploads/images/slider/ to http://alshamsiholdings.com/uploads/images/slider/
  server_path of id 2: http://localhost/alshamsiholdings/uploads/images/slider/ to http://alshamsiholdings.com/uploads/images/slider/
