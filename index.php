<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
    <link rel="stylesheet" href="styles.css" media="all">
</head>

<body>

    <div class="container">

        <h1>Task Manager</h1>
        <div class="task-input">
            <input type="text" id="task-input" placeholder="Enter a new task">
            <button onclick="addTask()">Add Task</button>
            <button class="delete-all-button" onclick="deleteAllTasks()">Delete All</button>
        </div>
        <ul id="task-list"></ul>
    </div>
    <button class="theme-toggle" onclick="toggleDarkMode()" aria-label="Toggle theme">
        <svg class="sun" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="6" fill="#ffd700" /> <!-- Increased radius from 4 to 6 -->
            <path class="sun-beams" d="M12 2v4M12 18v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M2 12h4M18 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" />
        </svg>
        <svg class="moon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446A9 9 0 1 1 12 3z" />
        </svg>
    </button>
    <script src="script.js"></script>
</body>

</html>