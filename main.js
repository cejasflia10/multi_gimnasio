const { app, BrowserWindow } = require('electron');
const path = require('path');

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

    // 👇 Abre directamente login.php en tu servidor Render
    mainWindow.loadURL('https://multi-gimnasio-51bq.onrender.com/login.php');

    // Si querés depurar errores:
    // mainWindow.webContents.openDevTools();

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => app.quit());
app.on('activate', () => {
    if (mainWindow === null) createWindow();
});
