```


## tests/UnitTest.php


```php
<?php

use PHPUnit\Framework\TestCase;
use Laika\Session\SessionManager;
use Laika\Session\Session;


class UnitTest extends TestCase
{    
    public function testRenderSimple()
    {
        SessionManager::config();
        Session::set('name', 'Showket');
        $this->assertNotNull(Session::get('name'), "Failed to Initialize Session");
    }
}
