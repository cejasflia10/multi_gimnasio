    <button type="submit">Guardar Cambios</button>
</form>

<!-- Botón de eliminar usuario -->
<form action="eliminar_usuario.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este usuario? Esta acción no se puede deshacer.');">
    <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
    <button type="submit" style="background-color: red; color: white; margin-top: 10px;">Eliminar Usuario</button>
</form>
