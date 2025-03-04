document.addEventListener('DOMContentLoaded', function () {
    fetchTasks();
});

function fetchTasks() {
    const taskList = document.getElementById('task-list');
    taskList.innerHTML = '<li class="loading">Loading tasks...</li>';

    fetch('api.php/tasks')
        .then(response => {
            if (!response.ok) throw new Error('Network response failed');
            return response.json();
        })
        .then(tasks => {
            renderTasks(tasks);
        })
        .catch(error => console.error('Error fetching tasks:', error));
}

function renderTasks(tasks) {
    const taskList = document.getElementById('task-list');
    taskList.innerHTML = '';
    tasks.forEach(task => {
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

function addTask() {
    const taskInput = document.getElementById('task-input');
    const taskTitle = taskInput.value.trim();

    if (!taskTitle) return;

    fetch('api.php/tasks', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json;charset=UTF-8'
        },
        body: JSON.stringify({ title: taskTitle })
    })
        .then(response => {
            if (!response.ok) throw new Error(`Failed to add task: ${response.status}`);
            return response.json();
        })
        .then(() => {
            taskInput.value = '';
            fetchTasks();
        })
        .catch(error => {
            console.error('Error adding task:', error);
        })
        .finally(() => {
        });
}

function deleteTask(id) {
    fetch(`api.php/tasks/${id}`, {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to delete task');
            fetchTasks();
        })
        .catch(error => console.error('Error deleting task:', error));
}

function deleteAllTasks() {
    fetch('api.php/tasks', {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to delete all tasks');
            fetchTasks();
        })
        .catch(error => console.error('Error deleting all tasks:', error));
}

function updateTaskStatus(id, newStatus) {
    fetch(`api.php/tasks/${id}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json;charset=UTF-8'
        },
        body: JSON.stringify({ status: newStatus })
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to update task status');
            fetchTasks();
        })
        .catch(error => console.error('Error updating task status:', error));
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