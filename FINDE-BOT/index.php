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

        try {
            // ==========================================
            // SECCIÓN: LOGIN A SALESFORCE
            // ==========================================
            
            $driver->get('https://login.salesforce.com/?locale=mx');
            $driver->executeScript('window.localStorage.clear();');
            $driver->executeScript('window.sessionStorage.clear();');

            $driver->findElement(WebDriverBy::name('username'))->sendKeys($username);
            $driver->findElement(WebDriverBy::name('pw'))->sendKeys($password);
            $driver->findElement(WebDriverBy::id('Login'))->click();

            // ==========================================
            // SECCIÓN: CONFIGURACIÓN DE ARCHIVOS
            // ==========================================
            
            // Archivo de control de procesos
            $archivo = 'View/tip_Finde/process.txt';
            $gestor = fopen($archivo, 'r');
            if (!$gestor) {
                die("No se pudo abrir el archivo 'process.txt'.\n");
            }
            fclose($gestor);

            // ==========================================
            // SECCIÓN: CARGAR MENSAJES PREDEFINIDOS
            // ==========================================
            
            $csvMensajes = 'View/tip_Finde/process.txt';
            $mensajesData = [];

            if (file_exists($csvMensajes)) {
                $csvFile = fopen($csvMensajes, 'r');
                $headers = fgetcsv($csvFile);
                
                while (($row = fgetcsv($csvFile)) !== false) {
                    $mensajesData[] = [
                        'Mensaje' => $row[0],
                        'Estado' => $row[1],
                        'Asunto' => $row[2],
                        'Descripcion' => $row[3]
                    ];
                }
                fclose($csvFile);
                echo "Mensajes cargados: " . count($mensajesData) . " registros\n";
            } else {
                die("No se encontró el archivo mensajes.csv\n");
            }

            // Esperar a que Salesforce cargue completamente después del login
            sleep(30);

            // ==========================================
            // SECCIÓN: CONFIGURACIÓN OMNICANAL
            // ==========================================
            
            try {
                $utilityButton = $driver->findElement(
                    WebDriverBy::xpath("//span[contains(text(), 'OmniCanal') and contains(text(), 'Sin conexión')]/parent::button")
                );
                if ($utilityButton->isDisplayed()) {
                    $utilityButton->click();
                    echo "Boton OmniCanal clickeado\n";
                    sleep(2);
                }
            } catch (Exception $e) {
                echo "No se encontró el boton OmniCanal (Sin conexión): " . $e->getMessage() . "\n";
            }

            try {
                // Opción 1: Buscar específicamente en el área de OmniCanal/utility bar
                $specificButton = $driver->findElement(
                    WebDriverBy::xpath("//div[contains(@class, 'utilityBar')]//button[.//svg[@data-key='down']]")
                );
                
                if ($specificButton->isDisplayed()) {
                    $specificButton->click();
                    echo "Boton desplegable clickeado\n";
                    sleep(2);
                }
            } catch (Exception $e) {
                // Opción 2: Si la primera falla, buscar por el tabindex que viste antes
                try {
                    $specificButton = $driver->findElement(
                        WebDriverBy::xpath("//button[@tabindex='0'][@aria-expanded='false'][.//svg[@data-key='down']]")
                    );
                    
                    if ($specificButton->isDisplayed()) {
                        $specificButton->click();
                        echo "Boton desplegable clickeado\n";
                        sleep(2);
                    }
                } catch (Exception $e2) {
                    echo "No se encontró button deslizable: " . $e2->getMessage() . "\n";
                }
            }

            try {
                $multiSkillElement = $wait->until(
                    WebDriverExpectedCondition::elementToBeClickable(
                        WebDriverBy::xpath("//span[contains(text(), 'Multi skill Disponible Hogares')]")
                    )
                );
                $multiSkillElement->click();
                echo "Multi skill Disponible Hogares seleccionado\n";
                sleep(2);
            } catch (Exception $e) {
                echo "No se encontró 'Multi skill Disponible Hogares': " . $e->getMessage() . "\n";
            }

            // ==========================================
            // BUCLE PRINCIPAL: MONITOREO CONTINUO DE CHATS
            // ==========================================

            $chatProcessingActive = true;
            $waitInterval = 5;

            echo "Iniciando monitoreo continuo de chats entrantes...\n";

            while ($chatProcessingActive) {

                try {
                    // Intentar localizar un chat disponible en la consola
                    $specificChat = $driver->findElement(
                        WebDriverBy::xpath("//li[@role='presentation' and contains(@class, 'navexConsoleTabItem')]")
                    );
                    
                    if ($specificChat->isDisplayed()) {
                        echo "Chat encontrado! Iniciando procesamiento...\n";
                        
                        // Hacer clic en el chat para abrirlo
                        $specificChat->click();
                        sleep(3);
                        
                        // ==========================================
                        // SECCIÓN: COMPOSICIÓN Y ENVÍO DEL MENSAJE
                        // ==========================================

                        try {
                            // Verificar si el textarea del mensaje existe y está habilitado
                            $textareaFound = false;
                            $textareaEnabled = false;
                            $newMessage = null;
                            
                            try {
                                // Buscar el textarea SIN la condición not(@disabled) primero
                                $newMessage = $driver->findElement(
                                    WebDriverBy::xpath("//textarea[@class='slds-textarea messaging-textarea']")
                                );
                                $textareaFound = true;
                                
                                // Verificar explícitamente si está habilitado
                                $isDisabled = $newMessage->getAttribute('disabled');
                                $textareaEnabled = ($isDisabled === null || $isDisabled === false) && $newMessage->isEnabled();
                                
                                if (!$textareaEnabled) {
                                    echo "Textarea encontrado pero está deshabilitado\n";
                                }
                                
                            } catch (NoSuchElementException $e) {
                                echo "Textarea no encontrado, pasando a tipificación directamente\n";
                            }
                            
                            // Solo intentar escribir si el textarea está habilitado
                            if ($textareaFound && $textareaEnabled) {
                                echo "Textarea habilitado, escribiendo mensaje...\n";
                                
                                // Scroll al elemento para asegurarse que esté visible
                                $driver->executeScript("arguments[0].scrollIntoView(true);", [$newMessage]);
                                sleep(1);
                                
                                // Click con JavaScript para evitar conflictos
                                $driver->executeScript("arguments[0].click();", [$newMessage]);
                                sleep(1);
                                
                                $newMessage->clear();
                                $newMessage->sendKeys($mensajesData[0]['Mensaje']);
                                echo "Mensaje escrito en el campo\n";
                                sleep(2);
                                
                                // Enviar el mensaje
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
                                
                            } else {
                                echo "Textarea deshabilitado o no disponible, omitiendo envío de mensaje\n";
                                echo "Procediendo directamente a la tipificación...\n";
                            }
                            
                        } catch (Exception $e) {
                            echo "Error verificando textarea: " . $e->getMessage() . "\n";
                            echo "Continuando con tipificación...\n";
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
                                echo "Panel de tipificacion abierto\n";
                                sleep(2);
                            }
                        } catch (Exception $e) {
                            echo "No se encontró el icono de tipificación: " . $e->getMessage() . "\n";
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
                                echo "Combobox de estado abierto\n";
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
                                echo "Estado 'Resuelto' seleccionado\n";
                                sleep(2);
                            }
                        } catch (Exception $e) {
                            echo "No se encontró la opción 'Resuelto': " . $e->getMessage() . "\n";
                        }

                        // ==========================================
                        // SECCIÓN: ASIGNACIÓN DEL ASUNTO
                        // ==========================================
                        
                        try {
                            $asuntoInput = $driver->findElement(
                                WebDriverBy::xpath("//input[@type='text' and @maxlength='255']")
                            );
                            
                            if ($asuntoInput->isDisplayed()) {
                                $asuntoInput->click();
                                sleep(1);
                                $asuntoInput->clear();
                                $asuntoInput->sendKeys($mensajesData[0]['Asunto']);
                                echo "Asunto ingresado\n";
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
                                echo "Descripcion ingresada\n";
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
                                echo "Tipificación guardada correctamente\n";
                                sleep(2);
                            }
                        } catch (Exception $e) {
                            echo "No se encontró el botón Guardar: " . $e->getMessage() . "\n";
                        }
                        
                        echo "Chat procesado completamente. Buscando siguiente chat...\n";
                        sleep(3);
                    }
                
                } catch (NoSuchElementException $e) {
                    // No hay chats disponibles en este momento
                    echo "Sin chats disponibles, esperando... (" . date('H:i:s') . ")\n";
                    sleep($waitInterval);
                    
                } catch (Exception $e) {
                    // Capturar cualquier otro error inesperado
                    echo "Error inesperado: " . $e->getMessage() . "\n";
                    sleep($waitInterval);
                }
            }

        } catch (TimeoutException $e) {
            echo "Error: Tiempo excedido - " . $e->getMessage() . "\n";
            if (isset($driver)) {
                $driver->quit();
            }
        } catch (Exception $e) {
            echo "Error general: " . $e->getMessage() . "\n";
            if (isset($driver)) {
                $driver->quit();
            }
        }
        
        // Nota: El driver NO se cierra aquí para mantener el bucle activo
        // Si necesitas cerrar el navegador, presiona Ctrl+C en la terminal
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
                        <input type="text" id="validationCustomUsername" name="username" placeholder="Usuario_Salesforce" value="maria.martinb.pr@etb.com.co" required>
                    </div>

                    <div>
                        <img src="View/images/icons/pass.png" alt="Icono de contraseña">
                        <input type="password" id="dz-password" name="password" placeholder="Contraseña_Salesforce" value="Colombia2025" required>
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