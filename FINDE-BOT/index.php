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
            '--window-size=1920,1080'
        ]);

        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create($host, $capabilities);
        $wait = new WebDriverWait($driver, 30);

        $driver->get('https://login.salesforce.com/?locale=mx');
        $driver->executeScript('window.localStorage.clear();');
        $driver->executeScript('window.sessionStorage.clear();');

        $driver->findElement(WebDriverBy::name('username'))->sendKeys($username);
        $driver->findElement(WebDriverBy::name('pw'))->sendKeys($password);
        $driver->findElement(WebDriverBy::id('Login'))->click();

        $archivo = 'View/tip_Finde/process.txt';
        $gestor = fopen($archivo, 'r');
        if (!$gestor) die("No se pudo abrir el archivo 'process.txt'.\n");

        $filePath = 'View/tip_Finde/Gestiones_Realizadas.csv';
        $fileExists2 = file_exists($filePath);
        $file = fopen($filePath, 'a');
        if (!$file) die("No se pudo abrir o crear el archivo CSV.\n");

        if (!$fileExists2) {
            fputcsv($file, [
                'Espacio1','Espacio2'
            ]);
        }

        try {

            sleep(30);

            try {
                $utilityButton = $driver->findElement(
                    WebDriverBy::xpath("//span[contains(text(), 'OmniCanal') and contains(text(), 'Sin conexión')]/parent::button")
                );
                if ($utilityButton->isDisplayed()) {
                    $utilityButton->click();
                    sleep(2);
                }
            } catch (Exception $e) {
                echo "No se encontró el boton OmniCanal (Sin conexión): " . $e->getMessage() . "\n";
            }

            try {
                $specificButton = $driver->findElement(
                    WebDriverBy::xpath("//button[@tabindex='0' and @aria-expanded='false' and @aria-haspopup='true']")
                );
                if ($specificButton->isDisplayed()) {
                    $specificButton->click();
                    sleep(2);
                }
            } catch (Exception $e) {
                echo "No se encontró button deslizable: " . $e->getMessage() . "\n";
            }

            try {
            $multiSkillElement = $wait->until(
                WebDriverExpectedCondition::elementToBeClickable(
                    WebDriverBy::xpath("//span[contains(text(), 'Multi skill Disponible Hogares')]")
                )
            );
                $multiSkillElement->click();
                sleep(2);
            } catch (Exception $e) {
                echo "No se encontró 'Multi skill Disponible Hogares': " . $e->getMessage() . "\n";
            }

            // ==========================================
    // BUCLE PRINCIPAL: MONITOREO CONTINUO DE CHATS
    // ==========================================

    $chatProcessingActive = true;
    $waitInterval = 5; // Intervalo de verificación en segundos

    echo "🔄 Iniciando monitoreo continuo de chats entrantes...\n";

    while ($chatProcessingActive) {
        try {
            // Intentar localizar un chat disponible en la consola
            $specificChat = $driver->findElement(
                WebDriverBy::xpath("//li[@role='presentation' and contains(@class, 'navexConsoleTabItem')]")
            );
            
            if ($specificChat->isDisplayed()) {
                echo "📥 Chat encontrado! Iniciando procesamiento...\n";
                
                // Hacer clic en el chat para abrirlo
                $specificChat->click();
                sleep(2);
                
                // ==========================================
                // SECCIÓN: COMPOSICIÓN Y ENVÍO DEL MENSAJE
                // ==========================================
                
                try {
                    $newMessage = $driver->findElement(
                        WebDriverBy::xpath("//textarea[@class='slds-textarea messaging-textarea']")
                    );
                    
                    if ($newMessage->isDisplayed()) {
                        $newMessage->click();
                        sleep(1);
                        $newMessage->clear();
                        $newMessage->sendKeys($mensajesData[0]['Mensaje']);
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el campo de escritura: " . $e->getMessage() . "\n";
                }

                try {
                    $sendButton = $driver->findElement(
                        WebDriverBy::xpath("//button[@aria-label='Enviar' and contains(@class, 'slds-button')]")
                    );
                    
                    if ($sendButton->isDisplayed() && $sendButton->isEnabled()) {
                        $sendButton->click();
                        echo "Mensaje enviado correctamente\n";
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el botón de enviar: " . $e->getMessage() . "\n";
                }

                // ==========================================
                // SECCIÓN: APERTURA DEL PANEL DE TIPIFICACIÓN
                // ==========================================
                
                try {
                    $menuTipifications = $driver->findElement(
                        WebDriverBy::xpath("//lightning-primitive-icon[@variant='bare']")
                    );
                    if ($menuTipifications->isDisplayed()) {
                        $menuTipifications->click();
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el campo de tipificación de estado: " . $e->getMessage() . "\n";
                }

                // ==========================================
                // SECCIÓN: SELECCIÓN DEL ESTADO
                // ==========================================
                
                try {
                    $newStatus = $driver->findElement(
                        WebDriverBy::xpath("//a[@role='combobox'][@aria-label='select']")
                    );
                    if ($newStatus->isDisplayed()) {
                        $newStatus->click();
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el campo de tipificación de estado: " . $e->getMessage() . "\n";
                }

                try {
                    $opcionResuelto = $driver->findElement(
                        WebDriverBy::xpath("//a[@role='option'][@title='Resuelto']")
                    );
                    if ($opcionResuelto->isDisplayed()) {
                        $opcionResuelto->click();
                        sleep(2);
                        echo "Estado 'Resuelto' seleccionado\n";
                    }
                } catch (Exception $e) {
                    echo "No se encontró la opción 'Resuelto': " . $e->getMessage() . "\n";
                }

                // ==========================================
                // SECCIÓN: ASIGNACIÓN DEL ASUNTO
                // ==========================================
                
                try {
                    $asuntoInput = $driver->findElement(
                        WebDriverBy::xpath("//input[@aria-labelledby='4171:0-label']")
                    );
                    
                    if ($asuntoInput->isDisplayed()) {
                        $asuntoInput->click();
                        sleep(1);
                        $asuntoInput->clear();
                        $asuntoInput->sendKeys($mensajesData[0]['Asunto']);
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el campo de tipificación del asunto: " . $e->getMessage() . "\n";
                }

                // ==========================================
                // SECCIÓN: ASIGNACIÓN DE LA DESCRIPCIÓN
                // ==========================================
                
                try {
                    $newDescription = $driver->findElement(
                        WebDriverBy::xpath("//textarea[@role='textbox' and @maxlength='32000']")
                    );
                    
                    if ($newDescription->isDisplayed()) {
                        $newDescription->click();
                        sleep(1);
                        $newDescription->clear();
                        $newDescription->sendKeys($mensajesData[0]['Descripcion']);
                        sleep(2);
                    }
                } catch (Exception $e) {
                    echo "No se encontró el campo de tipificación de la descripción: " . $e->getMessage() . "\n";
                }

                // ==========================================
                // SECCIÓN: GUARDAR LA TIPIFICACIÓN
                // ==========================================
                
                try {
                    $saveButton = $driver->findElement(
                        WebDriverBy::xpath("//span[@class='label bBody' and contains(text(), 'Guardar')]")
                    );
                    
                    if ($saveButton->isDisplayed()) {
                        $saveButton->click();
                        sleep(2);
                        echo "Tipificación guardada correctamente\n";
                    }
                } catch (Exception $e) {
                    echo "No se encontró el botón Guardar: " . $e->getMessage() . "\n";
                }
                
                echo "✅ Chat procesado completamente. Buscando siguiente chat...\n";
                sleep(3);
            }
        
        } catch (NoSuchElementException $e) {
            // No hay chats disponibles en este momento
            echo "⏳ Sin chats disponibles, esperando... (" . date('H:i:s') . ")\n";
            sleep($waitInterval);
            
        } catch (Exception $e) {
            // Capturar cualquier otro error inesperado
            echo "❌ Error inesperado: " . $e->getMessage() . "\n";
            sleep($waitInterval);
        }
    }

            } catch (TimeoutException | NoSuchElementException $e) {
                
                echo "<script>alert('Error: Tiempo excedido');</script>";
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
                        <input type="text" id="validationCustomUsername" name="username" placeholder="Usuario_Salesforce" value="sergio.peraltab@etb.com.co" required>
                    </div>

                    <div>
                        <img src="View/images/icons/pass.png" alt="Icono de contraseña">
                        <input type="password" id="dz-password" name="password" placeholder="Contraseña_Salesforce" value="Colombia27" required>
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