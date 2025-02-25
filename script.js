document.addEventListener('DOMContentLoaded', function () {
    fetchTasks();
});

function fetchTasks() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'api.php/tasks', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            var tasks = JSON.parse(xhr.responseText);
            var taskList = document.getElementById('task-list');
            taskList.innerHTML = '';
            tasks.forEach(function (task) {
                var li = document.createElement('li');
                li.textContent = task.title;

                var statusButton = document.createElement('button');
                statusButton.textContent = task.status;
                statusButton.className = 'task-status ' + task.status;
                statusButton.onclick = function () {
                    updateTaskStatus(task.id, task.status === 'pending' ? 'done' : 'pending');
                };

                var deleteButton = document.createElement('button');
                deleteButton.textContent = 'Delete';
                deleteButton.onclick = function () {
                    deleteTask(task.id);
                };

                li.appendChild(statusButton);
                li.appendChild(deleteButton);
                taskList.appendChild(li);
            });
        }
    };
    xhr.send();
}

function addTask() {
    var taskInput = document.getElementById('task-input');
    var taskTitle = taskInput.value;
    if (taskTitle) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php/tasks', true);
        xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
        xhr.onload = function () {
            if (xhr.status === 200) {
                taskInput.value = '';
                fetchTasks();
            }
        };
        xhr.send(JSON.stringify({ title: taskTitle }));
    }
}

function deleteTask(id) {
    var xhr = new XMLHttpRequest();
    xhr.open('DELETE', 'api.php/tasks/' + id, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            fetchTasks();
        }
    };
    xhr.send();
}

function deleteAllTasks() {
    var xhr = new XMLHttpRequest();
    xhr.open('DELETE', 'api.php/tasks', true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            fetchTasks();
        }
    };
    xhr.send();
}

function updateTaskStatus(id, newStatus) {
    var xhr = new XMLHttpRequest();
    xhr.open('PATCH', 'api.php/tasks/' + id + '/status', true);
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.onload = function () {
        if (xhr.status === 200) {
            fetchTasks();
        }
    };
    xhr.send(JSON.stringify({ status: newStatus }));
}

function toggleDarkMode() {
    let currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    document.documentElement.classList.remove('system-dark');

    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    } else {
        document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        if (prefersDark) {
            document.documentElement.classList.add('system-dark');
        }
    }
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!localStorage.getItem('theme')) {
        document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        document.documentElement.classList.toggle('system-dark', e.matches);
    }
});