<?php
// Fichier: /src/Controller/StreamController.php

namespace App\Controller;

use Predis\Client as RedisClient;

class StreamController
{
    private RedisClient $redis;

    public function __construct(RedisClient $redis)
    {
        $this->redis = $redis;
    }

    public function handleStream(): void
    {
        // Headers essentiels pour Server-Sent Events
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Important pour nginx
        
        // Nettoyer les buffers de sortie
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo "retry: 10000\n\n"; // Si la connexion est perdue, réessaie après 10s
        flush();

        try {
            $pubsub = $this->redis->pubSubLoop();
            
            // S'abonner au canal que le worker utilise
            $pubsub->subscribe('widget_updates');

            foreach ($pubsub as $message) {
                if ($message->kind === 'message') {
                    $serviceId = $message->payload;
                    
                    // L'ID du service est publié, nous récupérons les données
                    // complètes depuis le cache
                    $data = $this->redis->get("widget:data:" . $serviceId);

                    if ($data) {
                        // Formater le message SSE
                        echo "event: widget_update\n";
                        
                        // Envoyer l'ID ET les données (payload)
                        $payload = json_encode([
                            'serviceId' => $serviceId,
                            'payload' => $data // $data est déjà une chaîne JSON
                        ]);
                        echo "data: " . $payload . "\n\n";

                        // Pousser les données au client immédiatement
                        flush();
                    }
                }

                // Vérifier si le client s'est déconnecté
                if (connection_aborted()) {
                    break;
                }
            }

            $pubsub->unsubscribe();

        } catch (\Exception $e) {
            // Gérer les erreurs de connexion Redis
            error_log("Erreur Stream SSE: " . $e->getMessage());
        }
    }
}
