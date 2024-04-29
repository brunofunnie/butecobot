<?php

$app = [
    'master_role' => ['Admin', 'Gerente'],
    'admin_role' => ['Moderador', 'Sub Moderador', 'Bot Manager⚙️'],
];

$app['admin_role'] = array_merge($app['admin_role'], $app['master_role']);

return $app;
