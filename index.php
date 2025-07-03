<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A simple and clean Task Manager web app.">
    <title>Task Manager</title>
    <link rel="stylesheet" href="styles.css" media="all">
</head>

<body>
    <main class="container">
        <header>
            <h1>Task Manager</h1>
        </header>
        <section class="task-input" aria-label="Add new task">
            <input type="text" id="task-input" placeholder="Enter a new task" aria-label="Task input">
            <button id="add-task-btn">Add Task</button>
            <button class="delete-all-button" id="delete-all-btn">
                Delete All
            </button>
        </section>
        <ul id="task-list" aria-label="Task list"></ul>
    </main>
    <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
        <svg class="sun" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="12" cy="12" r="6" fill="#ffd700" />
            <path class="sun-beams" d="M12 2v4M12 18v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M2 12h4M18 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" />
        </svg>
        <svg class="moon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446A9 9 0 1 1 12 3z" />
        </svg>
    </button>
    <script src="script.js"></script>
    <script>
        document.getElementById('add-task-btn').addEventListener('click', addTask);
        document.getElementById('delete-all-btn').addEventListener('click', deleteAllTasks);
        document.getElementById('theme-toggle').addEventListener('click', toggleDarkMode);
    </script>
</body>

</html>
