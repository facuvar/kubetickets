@echo off
echo ========================================
echo   DEPLOY KUBETICKETS A GITHUB
echo ========================================
echo.

echo [1/6] Inicializando repositorio git...
git init
if errorlevel 1 goto error

echo [2/6] Agregando archivos...
git add .
if errorlevel 1 goto error

echo [3/6] Creando commit inicial...
git commit -m "Deploy inicial - Sistema Tickets KubeAgency - Localhost + Railway compatible"
if errorlevel 1 goto error

echo [4/6] Configurando rama principal...
git branch -M main
if errorlevel 1 goto error

echo [5/6] Agregando repositorio remoto...
git remote add origin https://github.com/facuvar/kubetickets.git
if errorlevel 1 goto error

echo [6/6] Subiendo a GitHub...
git push -u origin main
if errorlevel 1 goto error

echo.
echo ========================================
echo   DEPLOY COMPLETADO EXITOSAMENTE!
echo ========================================
echo.
echo Tu codigo esta ahora en:
echo https://github.com/facuvar/kubetickets
echo.
echo Proximo paso:
echo 1. Ve a Railway Dashboard
echo 2. Crea nuevo proyecto desde GitHub
echo 3. Conecta el repositorio kubetickets
echo 4. Agrega servicio MySQL
echo 5. Configura variables de entorno
echo.
pause
goto end

:error
echo.
echo ERROR: Algo salio mal en el deploy
echo Revisa los mensajes de error arriba
echo.
pause

:end 