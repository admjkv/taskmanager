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
    <script src="script.js"></script>
</body>
</html>
