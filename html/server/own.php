<?php

//When not in use, you can uncomment the following information so that others can access this information
//if (!preg_match('/cli/i', php_sapi_name())) {
//exit('require php-cli mode');
//}

$require_functions = ['shell_exec'];
$disable_functions = explode(',', ini_get('disable_functions'));
foreach ($disable_functions as $disable) {
    if (in_array(trim($disable), $require_functions)) {
        exit("The required $disable function is disabled");
    }
}

print_r(shell_exec('id -a'));