<?php
require __DIR__ . '/config.php';

unset($_SESSION['user_id']);
session_regenerate_id(true);
flash('info', 'Kamu sudah keluar. Sampai jumpa lagi!');
redirect('index.php');
