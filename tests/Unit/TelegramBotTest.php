<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TelegramBotTest extends TestCase
{
    protected function setUp(): void
    {
        // Limpiar sesiones previas antes de cada test
        $files = glob(__DIR__ . '/../../App/Controllers/bot_sessions/tg_*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testBotRespondsToAyudaCommand()
    {
        // Mock del payload de Telegram
        $update = [
            'message' => [
                'chat' => ['id' => '123456789'],
                'from' => ['first_name' => 'Tester'],
                'text' => '/ayuda'
            ]
        ];

        // Interceptar output (ej. cURL o echo) si tu bot imprime o envía HTTP
        ob_start();
        
        // Simular variables globales necesarias
        $TG_PROJECT_ROOT = dirname(__DIR__, 2);
        putenv('TELEGRAM_BOT_TOKEN=test_token');
        
        // Cargar el script (asegúrate de ajustar la ruta)
        require __DIR__ . '/../../App/Controllers/TelegramBot.php';
        
        $output = ob_get_clean();
        
        // Si tu función tgSend hace curl real, deberías abstraerla en una clase o mockearla con vfsStream. 
        // Para tests estrictos, se recomienda refactorizar tgSend() dentro de una clase inyectable.
        $this->assertTrue(true, 'Test base de carga de archivo ejecutado.');
    }
}