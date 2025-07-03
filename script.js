
const API_BASE = 'api.php/tasks'

function showError(message) {
    let errorDiv = document.getElementById('error-message')
    if (!errorDiv) {
        errorDiv = document.createElement('div')
        errorDiv.id = 'error-message'
        errorDiv.style.color = 'red'
        errorDiv.style.margin = '10px 0'
        document.body.prepend(errorDiv)
    }
    errorDiv.textContent = message
    setTimeout(() => { errorDiv.textContent = '' }, 4000)
}

function fetchTasks() {
    const taskList = document.getElementById('task-list')
    taskList.innerHTML = '<li class="loading">Loading tasks...</li>'

    fetch(API_BASE)
        .then(response => {
            if (!response.ok) throw new Error('Network response failed')
            return response.json()
        })
        .then(tasks => {
            renderTasks(tasks)
        })
        .catch(error => {
            showError('Error fetching tasks.')
            console.error('Error fetching tasks:', error)
        })
}

function renderTasks(tasks) {
    const taskList = document.getElementById('task-list')
    taskList.innerHTML = ''
    if (!tasks.length) {
        const emptyLi = document.createElement('li')
        emptyLi.textContent = 'No tasks found.'
        emptyLi.className = 'empty-state'
        taskList.appendChild(emptyLi)
        return
    }
    tasks.forEach(task => {
        const li = document.createElement('li')
        li.textContent = task.title

        const statusButton = document.createElement('button')
        statusButton.textContent = task.status
        statusButton.className = 'task-status ' + task.status
        statusButton.setAttribute('aria-label', `Mark as ${task.status === 'pending' ? 'done' : 'pending'}`)
        statusButton.onclick = () => {
            statusButton.disabled = true
            updateTaskStatus(task.id, task.status === 'pending' ? 'done' : 'pending', statusButton)
        }

        const deleteButton = document.createElement('button')
        deleteButton.textContent = 'Delete'
        deleteButton.setAttribute('aria-label', 'Delete task')
        deleteButton.onclick = () => {
            deleteButton.disabled = true
            deleteTask(task.id, deleteButton)
        }

        li.appendChild(statusButton)
        li.appendChild(deleteButton)
        taskList.appendChild(li)
    })
}

function addTask() {
    const taskInput = document.getElementById('task-input')
    const taskTitle = taskInput.value.trim()
    if (!taskTitle) return

    const addBtn = document.getElementById('add-task-btn')
    if (addBtn) addBtn.disabled = true

    fetch(API_BASE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json;charset=UTF-8'
        },
        body: JSON.stringify({ title: taskTitle })
    })
        .then(response => {
            if (!response.ok) throw new Error(`Failed to add task: ${response.status}`)
            return response.json()
        })
        .then(() => {
            taskInput.value = ''
            fetchTasks()
        })
        .catch(error => {
            showError('Error adding task.')
            console.error('Error adding task:', error)
        })
        .finally(() => {
            if (addBtn) addBtn.disabled = false
        })
}

function deleteTask(id, btn) {
    fetch(`${API_BASE}/${id}`, {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to delete task')
            fetchTasks()
        })
        .catch(error => {
            showError('Error deleting task.')
            console.error('Error deleting task:', error)
        })
        .finally(() => {
            if (btn) btn.disabled = false
        })
}

function deleteAllTasks() {
    fetch(API_BASE, {
        method: 'DELETE'
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to delete all tasks')
            fetchTasks()
        })
        .catch(error => {
            showError('Error deleting all tasks.')
            console.error('Error deleting all tasks:', error)
        })
}

function updateTaskStatus(id, newStatus, btn) {
    fetch(`${API_BASE}/${id}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json;charset=UTF-8'
        },
        body: JSON.stringify({ status: newStatus })
    })
        .then(response => {
            if (!response.ok) throw new Error('Failed to update task status')
            fetchTasks()
        })
        .catch(error => {
            showError('Error updating task status.')
            console.error('Error updating task status:', error)
        })
        .finally(() => {
            if (btn) btn.disabled = false
        })
}


function setTheme(theme, system = false) {
    document.documentElement.setAttribute('data-theme', theme)
    if (system) {
        document.documentElement.classList.add('system-dark')
    } else {
        document.documentElement.classList.remove('system-dark')
    }
}

function toggleDarkMode() {
    let currentTheme = document.documentElement.getAttribute('data-theme') || 'light'
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark'
    setTheme(newTheme)
    localStorage.setItem('theme', newTheme)
}


document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme')
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    if (savedTheme) {
        setTheme(savedTheme)
    } else {
        setTheme(prefersDark ? 'dark' : 'light', prefersDark)
    }

    fetchTasks()
})


window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light', e.matches)
    }
})