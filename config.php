<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodleuser';
$CFG->dbpass    = 'StrongPasswordHere';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => 0,
    'dbport' => '',
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://yourdomain.com';
$CFG->dataroot  = '/srv/moodledata';
$CFG->admin     = 'admin';

// Default DB sessions
$CFG->session_handler_class = null;

// Optional: Memcached
// $CFG->session_handler_class = '\core\session\memcached';
// $CFG->session_memcached_host = '127.0.0.1';
// $CFG->session_memcached_port = 11211;

// Optional: Redis
// $CFG->session_handler_class = '\core\session\redis';
// $CFG->session_redis_host = '127.0.0.1';
// $CFG->session_redis_port = 6379;
// $CFG->session_redis_prefix = 'moodle_sess_';
