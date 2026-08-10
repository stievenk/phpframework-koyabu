<?php
namespace Koyabu\Webapi;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Ollama {

    private string $baseURL = 'http://localhost:11434';
    private string $model = 'gemma4';
    private array $messages = [];
    private array $options = [
        'temperature' => 0.5,
        'top_p' => 0.9,
        'seed' => 0,
        'num_predict' => 2048,
        'num_ctx' => 8192
    ];

    private array $response = [];
    private array $tools = [];
    private int $timeout = 300;

    public function __construct(array $config = []) {
        $this->init($config);
    }

    /*
    |--------------------------------------------------------------------------
    | INIT
    |--------------------------------------------------------------------------
    */

    public function init(array $config = [])
    {

        if (!empty($config['base_url'])) {
            $this->baseURL = rtrim($config['base_url'], '/');
        }

        if (!empty($config['model'])) {
            $this->model = $config['model'];
        }

        if (!empty($config['timeout'])) {
            $this->timeout = (int)$config['timeout'];
        }

        if (!empty($config['options'])) {
            $this->options = array_merge(
                $this->options,
                $config['options']
            );
        }

        return $this;

    }

    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    public function model(string $model)
    {
        $this->model = $model;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | OPTION
    |--------------------------------------------------------------------------
    */

    public function option(string $name, mixed $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | SYSTEM
    |--------------------------------------------------------------------------
    */

    public function system(string $text)
    {
        $this->messages[] = [
            'role' => 'system',
            'content' => $text
        ];
        return $this;

    }

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    public function user(string $text)
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $text
        ];
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | ASSISTANT
    |--------------------------------------------------------------------------
    */

    public function assistant(string $text)
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $text
        ];
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR
    |--------------------------------------------------------------------------
    */

    public function clear()
    {
        $this->messages = [];
        return $this;

    }

    /*
    |--------------------------------------------------------------------------
    | SET HISTORY
    |--------------------------------------------------------------------------
    */

    public function messages(array $messages)
    {
        $this->messages = $messages;
        return $this;

    }

    /*
    |--------------------------------------------------------------------------
    | GET HISTORY
    |--------------------------------------------------------------------------
    */

    public function getMessages()
    {
        return $this->messages;
    }

    /*
    |--------------------------------------------------------------------------
    | ASK
    |--------------------------------------------------------------------------
    */

    public function ask(string $prompt)
    {
        $this->clear();
        $this->user($prompt);
        $this->chat();
        return $this->response();
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT
    |--------------------------------------------------------------------------
    */

    public function chat()
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->messages,
            'stream' => false,
            'options' => $this->options
        ];

        if (!empty($this->tools)) {
            $payload['tools'] = $this->tools;
        }

        try {
            $client = new Client([
                'base_uri' => $this->baseURL,
                'timeout' => $this->timeout
            ]);

            $response = $client->post('/api/chat', [
                'json' => $payload
            ]);

            $this->response = json_decode(
                $response->getBody()->getContents(),
                true
            );

            if (!empty($this->response['message'])) {
                $this->messages[] = $this->response['message'];
            }
            return $this;
        }

        catch (RequestException $e) {
            $this->response = [
                'error' => true,
                'message' => $e->getMessage()
            ];
            return $this;

        }

    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    public function response()
    {
        return $this->response['message']['content'] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | RAW
    |--------------------------------------------------------------------------
    */

    public function raw()
    {
        return $this->response;
    }

    /*
    |--------------------------------------------------------------------------
    | TOOL
    |--------------------------------------------------------------------------
    */

    public function tool(array $tool)
    {
        $this->tools[] = $tool;
        return $this;
    }

}
?>