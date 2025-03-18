<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

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
     * Get all tasks
     * @return array
     */
    public function getTasks()
    {
        return $this->loadTasks();
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
            echo json_encode($taskManager->getTasks());
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
