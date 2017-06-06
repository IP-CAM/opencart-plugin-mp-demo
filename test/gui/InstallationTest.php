<?php



require_once __DIR__ .'/../vendor/autoload.php';



use PHPUnit\Framework\TestCase; 


class InstallationTest extends TestCase
{
    
    /**
     * @var RemoteWebDriver
     */
    protected $webDriver;
    
    protected function setUp()
    {
        
        $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', DesiredCapabilities::phantomjs());
        
    }
    
    public function testInstallExtension()
    { 
        $this->webDriver->get('http://www.google.com'); 
        $this->assertContains('Google', $this->webDriver->getTitle());
    }
    
    public function tearDown()
    {
        $this->webDriver->quit();
    }
}
