<?php
foreach ($errors as $field => $error) {
    foreach ($error as $key => $value) {
        echo $field . ' ' . str_replace('field', 'param', $value) . '<br>';
    }
}
?>