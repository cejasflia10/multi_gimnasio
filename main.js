const { app, BrowserWindow } = require('electron');
const path = require('path');
const http = require('http');

let mainWindow;

function createWindow() {
    mainWindow = new BrowserWindow({
        title: "MultiGym CJS",
        fullscreen: true,
        autoHideMenuBar: true,
        frame: false,
        icon: path.join(__dirname, 'resources', 'icono.ico'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    // Cargar el sistema desde Render (login)
    mainWindow.loadURL('https://multi-gimnasio-51bq.onrender.com/login.php');

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

// Mantener activo Render haciendo ping cada 14 minutos
setInterval(() => {
    http.get('https://multi-gimnasio-51bq.onrender.com');
}, 14 * 60 * 1000); // 14 minutos

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    // En Windows y Linux, cerrar la app al cerrar ventana
    if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
    // En macOS, volver a crear la ventana si está cerrada
    if (mainWindow === null) createWindow();
});
