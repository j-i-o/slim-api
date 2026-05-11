<?php

function validateEmail($email) {
    // Verificar formato de correo electrónico
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function validatePassword($password) {
    // Verificar longitud mínima
    if (strlen($password) < 8) {
        return false;
    }

    // Requiere al menos una mayúscula, una letra minúscula, un número y un carácter especial
    if (!preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || 
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[\W]/', $password)) { // Verificar que contenga un carácter especial
        return false;
    }

    return true;
}

function validateName($name) {
    // Verificar que el nombre no esté vacío y tenga al menos 2 caracteres
    return !empty($name);
}