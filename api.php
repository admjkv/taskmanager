<?php

declare(strict_types=1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

function checkRateLimit(): void {
    global $clientIP;
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($clientIP) . '.json';
    $rateData = [
        'count' => 0,
        'reset_time' => time() + 60
    ];
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (is_array($data)) {
            $rateData = $data;
        }
    }
    if (time() > $rateData['reset_time']) {
        $rateData = [
            'count' => 1,
            'reset_time' => time() + 60
        ];
    } else {
        $rateData['count']++;
    }
    file_put_contents($cacheFile, json_encode($rateData, JSON_THROW_ON_ERROR));
    if ($rateData['count'] > 30) {
        header('Retry-After: ' . ($rateData['reset_time'] - time()));
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again later.']);
        exit;
    }
}

checkRateLimit();

/**
 * Task Manager API
 * 
 * Endpoints:
 * - GET    /tasks      - Get all tasks
 * - POST   /tasks      - Create a new task
 * - PUT    /tasks/{id} - Update a task
 * - DELETE /tasks/{id} - Delete a task
 * - DELETE /tasks      - Delete all tasks
 * - PATCH  /tasks/{id}/status - Update task status
 */


enum TaskStatus: string {
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Done = 'done';
}

class TaskManager
{
    public function __construct(
        public readonly string $tasksFile
    ) {}

    /**
     * Load tasks from file
     */
    private function loadTasks(): array
    {
        try {
            if (!file_exists($this->tasksFile)) {
                return [];
            }
            $fp = fopen($this->tasksFile, 'r');
            if ($fp === false) {
                throw new RuntimeException("Failed to open file for reading: {$this->tasksFile}");
            }
            flock($fp, LOCK_SH);
            $contents = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $tasks = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            return is_array($tasks) ? $tasks : [];
        } catch (\JsonException $e) {
            error_log("JSON decode error: " . $e->getMessage());
            return [];
        } catch (\Throwable $e) {
            error_log("Error loading tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save tasks to file (with file locking)
     */
    private function saveTasks(array $tasks): void
    {
        try {
            if (!is_dir(dirname($this->tasksFile))) {
                mkdir(dirname($this->tasksFile), 0755, true);
            }
            $fp = fopen($this->tasksFile, 'c+');
            if ($fp === false) {
                throw new RuntimeException("Failed to open file for writing: {$this->tasksFile}");
            }
            flock($fp, LOCK_EX);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($tasks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (\Throwable $e) {
            error_log("Error saving tasks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(int $id, TaskStatus|string $status): ?array
    {
        $status = $status instanceof TaskStatus ? $status : TaskStatus::tryFrom($status) ?? TaskStatus::Pending;
        $tasks = $this->loadTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] === $id) {
                $task['status'] = $status->value;
                $this->saveTasks($tasks);
                return $task;
            }
        }
        return null;
    }

    /**
     * Get tasks with optional pagination
     */
    public function getTasks(?int $page = null, ?int $limit = null): array
    {
        $tasks = $this->loadTasks();
        if ($page === null || $limit === null) {
            return [
                'success' => true,
                'data' => $tasks
            ];
        }
        $offset = ($page - 1) * $limit;
        return [
            'success' => true,
            'data' => [
                'tasks' => array_slice($tasks, $offset, $limit),
                'total' => count($tasks),
                'page' => $page,
                'limit' => $limit,
                'pages' => (int)ceil(count($tasks) / $limit)
            ]
        ];
    }

    /**
     * Add a new task
     */
    public function addTask(string $title, TaskStatus|string $status = TaskStatus::Pending): array
    {
        $title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
        if ($title === '') {
            throw new InvalidArgumentException("Task title cannot be empty", 400);
        }
        $status = $status instanceof TaskStatus ? $status : TaskStatus::tryFrom($status) ?? TaskStatus::Pending;
        $tasks = $this->loadTasks();
        $maxId = empty($tasks) ? 0 : max(array_map(fn($task) => $task['id'], $tasks));
        $newTask = [
            'id' => $maxId + 1,
            'title' => $title,
            'status' => $status->value,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $tasks[] = $newTask;
        $this->saveTasks($tasks);
        return [
            'success' => true,
            'data' => $newTask
        ];
    }

    /**
     * Update a task
     */
    public function updateTask(int $id, string $title, TaskStatus|string $status): ?array
    {
        $title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
        $status = $status instanceof TaskStatus ? $status : TaskStatus::tryFrom($status) ?? TaskStatus::Pending;
        $tasks = $this->loadTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] === $id) {
                $task['title'] = $title;
                $task['status'] = $status->value;
                $this->saveTasks($tasks);
                return [
                    'success' => true,
                    'data' => $task
                ];
            }
        }
        return null;
    }

    /**
     * Delete a task
     */
    public function deleteTask(int $id): bool
    {
        $tasks = $this->loadTasks();
        foreach ($tasks as $key => $task) {
            if ($task['id'] === $id) {
                array_splice($tasks, $key, 1);
                $this->saveTasks($tasks);
                return true;
            }
        }
        return false;
    }

    /**
     * Delete all tasks
     */
    public function deleteAllTasks(): void
    {
        $this->saveTasks([]);
    }
}

// Setup task manager

$taskManager = new TaskManager('tasks.json');
$method      = $_SERVER['REQUEST_METHOD'];
$input       = json_decode(file_get_contents('php://input'), true) ?? [];

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

$apiIndex = array_search('api.php', $pathParts);
if ($apiIndex !== false) {
    $endpoint = $pathParts[$apiIndex + 1] ?? '';
    $id = isset($pathParts[$apiIndex + 2]) ? (int)$pathParts[$apiIndex + 2] : null;
    $action = $pathParts[$apiIndex + 3] ?? null;
} else {
    $endpoint = '';
    $id = null;
    $action = null;
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

if ($endpoint === 'tasks') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

    $handler = match($method) {
        'GET'    => fn() => $taskManager->getTasks($page, $limit),
        'POST'   => fn() => $taskManager->addTask($input['title'] ?? '', $input['status'] ?? TaskStatus::Pending),
        'PUT'    => fn() => $taskManager->updateTask($id, $input['title'] ?? '', $input['status'] ?? TaskStatus::Pending),
        'DELETE' => fn() => $id !== null ?
            jsonResponse(['success' => $taskManager->deleteTask($id)], 204) :
            ($taskManager->deleteAllTasks() ?? jsonResponse(['success' => true], 204)),
        'PATCH'  => fn() => ($action === 'status') ? $taskManager->updateTaskStatus($id, $input['status'] ?? TaskStatus::Pending) : null,
        default  => fn() => throw new Exception('Method not allowed', 405),
    };

    try {
        $result = $handler();
        if ($result === null) {
            jsonResponse(['success' => false, 'message' => 'Not found'], 404);
        } elseif (is_array($result) && isset($result['success']) && $result['success'] === true) {
            jsonResponse($result);
        } elseif ($method === 'DELETE') {
            // Already handled above
        } else {
            jsonResponse(['success' => true, 'data' => $result]);
        }
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 400);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Endpoint not found'], 404);
}
