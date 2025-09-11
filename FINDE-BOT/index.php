<?php
session_start();
require 'vendor/autoload.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverWait;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $host = 'http://localhost:9515';

        $options = new ChromeOptions();
        $options->addArguments([
            '--disable-gpu',
            '--disable-infobars',
            '--disable-blink-features=AutomationControlled',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1200,720'
        ]);

        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create($host, $capabilities);

        $driver->get('https://login.salesforce.com/?locale=mx');
        $driver->executeScript('window.localStorage.clear();');
        $driver->executeScript('window.sessionStorage.clear();');

        $driver->findElement(WebDriverBy::name('username'))->sendKeys($username);
        $driver->findElement(WebDriverBy::name('pw'))->sendKeys($password);
        $driver->findElement(WebDriverBy::id('Login'))->click();

        try {

            $_SESSION['loggedin'] = true;
            ini_set('memory_limit', '512M');

            sleep(20); // Reducido de 3 a 2

            } catch (TimeoutException | NoSuchElementException $e) {
                
                echo "<script>alert('Error: {$e->getMessage()}');</script>";
                $driver->quit();
                
            }finally {

                $driver->quit();

            }    

    } 
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="View/css/style.css">
    <link rel="shortcut icon" href="View/images/icons/windows.png" />
    <title>Ges-Bot</title>
</head>
<body>

    <div class="container">
        
        <div class="inic" id="star3">

            <div class="help">
                <a href="Model/Documents/" target="_blank"><span>?</span></a>
            </div>

            <div class="tittle">
                <img src="View/images/icons/ases.png" alt="">
                <h1>FINDE-BOT</h1>
            </div>

            <?php

                // Generar un token CSRF si no existe
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

                // Obtener el token para usar en el formulario
                $csrfToken = $_SESSION['csrf_token'];
            ?>

            <form action="" method="POST">
                <div class="data">
                    <!-- Campo oculto para el token CSRF -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div>
                        <img src="View/images/icons/email.png" alt="Icono de correo electrónico">
                        <input type="text" id="validationCustomUsername" name="username" placeholder="Usuario_Salesforce" required>
                    </div>

                    <div>
                        <img src="View/images/icons/pass.png" alt="Icono de contraseña">
                        <input type="password" id="dz-password" name="password" placeholder="Contraseña_Salesforce" required>
                    </div>

                    <div class="buttons-act">
                        <button class="act" type="submit">Iniciar</button>
                    </div>
                </div>
            </form>

            <div class="version_2">
                <span>1.0.1</span>
            </div>

        </div>

        <div class="backdrop"></div>

    </div>

<script src="View/bootstrap/jquery.js"></script>
<script src="View/bootstrap/bootstrap.bundle.min.js"></script>
<script src="Controller/buttons_Action.js"></script>
</body>
</html>