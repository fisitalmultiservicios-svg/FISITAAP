<?php
declare(strict_types=1);
// Plantilla exclusiva de un entorno QA aislado. No contiene datos del hosting.
$key=(string)getenv('FISITAAP_QA_APP_KEY');
if(strlen($key)<32)throw new RuntimeException('Define FISITAAP_QA_APP_KEY con una clave aleatoria propia de al menos 32 caracteres.');
return [
    'db_host'=>getenv('FISITAAP_QA_DB_HOST')?:'127.0.0.1',
    'db_name'=>getenv('FISITAAP_QA_DB_NAME')?:'fisitaap_qa',
    'db_user'=>getenv('FISITAAP_QA_DB_USER')?:'fisitaap_qa',
    'db_pass'=>getenv('FISITAAP_QA_DB_PASSWORD')?:'',
    'app_url'=>getenv('FISITAAP_QA_URL')?:'http://localhost:18080',
    'app_key'=>$key,
    'debug'=>false,
    'openai_api_key'=>getenv('FISITAAP_QA_OPENAI_API_KEY')?:'',
];
