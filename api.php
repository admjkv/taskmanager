<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

function checkRateLimit() {
    $clientIP = $_SERVER['REMOTE_ADDR'];
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($clientIP) . '.json';
    
    // Initialize or load rate limit data
    if (file_exists($cacheFile)) {
        $rateData = json_decode(file_get_contents($cacheFile), true);
    } else {
        $rateData = [
            'count' => 0,
            'reset_time' => time() + 60 // 1 minute window
        ];
    }
    
    // Reset if window has passed
    if (time() > $rateData['reset_time']) {
        $rateData = [
            'count' => 1,
            'reset_time' => time() + 60
        ];
    } else {
        $rateData['count']++;
    }
    
    // Save updated rate data
    file_put_contents($cacheFile, json_encode($rateData));
    
    // Check if rate limit exceeded (e.g., 30 requests per minute)
    if ($rateData['count'] > 30) {
        header('Retry-After: ' . ($rateData['reset_time'] - time()));
        http_response_code(429); // Too Many Requests
        echo json_encode(['message' => 'Rate limit exceeded. Try again later.']);
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
 * - PATCH  /tasks/{id} - Update task status
 */

class TaskManager
{
    private $tasksFile;

    /**
     * TaskManager constructor
     * @param string $tasksFile
     */
    public function __construct($tasksFile)
    {
        $this->tasksFile = $tasksFile;
    }

    /**
     * Load tasks from file
     * @return array
     */
    private function loadTasks()
    {
        try {
            $tasks = file_exists($this->tasksFile) ? json_decode(file_get_contents($this->tasksFile), true) : null;
            return is_array($tasks) ? $tasks : [];
        } catch (Exception $e) {
            // Log error
            error_log("Error loading tasks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save tasks to file
     * @param array $tasks
     */
    private function saveTasks($tasks)
    {
        try {
            if (!is_dir(dirname($this->tasksFile))) {
                mkdir(dirname($this->tasksFile), 0755, true);
            }
            $result = file_put_contents($this->tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
            if ($result === false) {
                throw new Exception("Failed to write to file: " . $this->tasksFile);
            }
        } catch (Exception $e) {
            // Log error
            error_log("Error saving tasks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update task status
     * @param int $id
     * @param string $status
     * @return array|null
     */
    public function updateTaskStatus($id, $status)
    {
        $tasks = $this->loadTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] == $id) {
                $task['status'] = $status;
                $this->saveTasks($tasks);
                return $task;
            }
        }
        return null;
    }

    /**
     * Get tasks with optional pagination
     * @param int $page Page number (starting from 1)
     * @param int $limit Items per page
     * @return array
     */
    public function getTasks($page = null, $limit = null)
    {
        $tasks = $this->loadTasks();
        
        // Return all tasks if pagination is not requested
        if ($page === null || $limit === null) {
            return $tasks;
        }
        
        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;
        
        return [
            'tasks' => array_slice($tasks, $offset, $limit),
            'total' => count($tasks),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil(count($tasks) / $limit)
        ];
    }

    /**
     * Add a new task
     * @param string $title
     * @param string $status
     * @return array
     */
    public function addTask($title, $status = 'pending')
    {
        // Sanitize input
        $title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
        if (empty($title)) {
            throw new Exception("Task title cannot be empty");
        }
        
        // Validate status
        $validStatuses = ['pending', 'in_progress', 'completed'];
        if (!in_array($status, $validStatuses)) {
            $status = 'pending';
        }
        
        $tasks = $this->loadTasks();

        $maxId = 0;
        foreach ($tasks as $task) {
            if ($task['id'] > $maxId) {
                $maxId = $task['id'];
            }
        }

        $newTask = [
            'id'     => $maxId + 1,
            'title'  => $title,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $tasks[] = $newTask;
        $this->saveTasks($tasks);
        return $newTask;
    }

    /**
     * Update a task
     * @param int $id
     * @param string $title
     * @param string $status
     * @return array|null
     */
    public function updateTask($id, $title, $status)
    {
        $tasks = $this->loadTasks();
        foreach ($tasks as &$task) {
            if ($task['id'] == $id) {
                $task['title']  = $title;
                $task['status'] = $status;
                $this->saveTasks($tasks);
                return $task;
            }
        }
        return null;
    }

    /**
     * Delete a task
     * @param int $id
     * @return bool
     */
    public function deleteTask($id)
    {
        $tasks = $this->loadTasks();
        foreach ($tasks as $key => $task) {
            if ($task['id'] == $id) {
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
    public function deleteAllTasks()
    {
        $this->saveTasks([]);
    }
}

// Setup task manager
$taskManager = new TaskManager('tasks.json');
$method      = $_SERVER['REQUEST_METHOD'];
$input       = json_decode(file_get_contents('php://input'), true);

// Parse request URI
$requestUri       = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$endpointPosition = strpos($_SERVER['REQUEST_URI'], 'api.php') ? 1 : 0;
$endpoint         = isset($requestUri[$endpointPosition]) ? $requestUri[$endpointPosition] : '';
$id               = isset($requestUri[$endpointPosition + 1]) ? intval($requestUri[$endpointPosition + 1]) : null;

// Handle request by method
if ($endpoint === 'tasks') {
    switch ($method) {
        case 'GET':
            $page = isset($_GET['page']) ? intval($_GET['page']) : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
            echo json_encode($taskManager->getTasks($page, $limit));
            break;

        case 'POST':
            // Validate required fields
            if (empty($input) || !isset($input['title']) || trim($input['title']) === '') {
                http_response_code(400);
                echo json_encode(['message' => 'Title is required']);
                break;
            }
            
            $status = isset($input['status']) ? $input['status'] : 'pending';
            // Validate status value
            if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Invalid status value']);
                break;
            }
            
            $newTask = $taskManager->addTask($input['title'], $status);
            echo json_encode($newTask);
            break;

        case 'PUT':
            $updatedTask = $taskManager->updateTask($id, $input['title'], $input['status']);
            if ($updatedTask) {
                echo json_encode($updatedTask);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Task not found']);
            }
            break;

        case 'DELETE':
            if ($id !== null) {
                if ($taskManager->deleteTask($id)) {
                    echo json_encode(['message' => 'Task deleted']);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Task not found']);
                }
            } else {
                $taskManager->deleteAllTasks();
                echo json_encode(['message' => 'All tasks deleted']);
            }
            break;

        case 'PATCH':
            if ($id !== null && isset($input['status'])) {
                $updatedTask = $taskManager->updateTaskStatus($id, $input['status']);
                if ($updatedTask) {
                    echo json_encode($updatedTask);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Task not found']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Bad request']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            break;
    }
} else {
    http_response_code(404);
    echo json_encode(['message' => 'Endpoint not found']);
}
