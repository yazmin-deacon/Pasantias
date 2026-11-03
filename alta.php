
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Consultar los trabajos para el select =====
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY nombre_t";
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}
?>


        <p>Trabajo:
            <select name="id_trabajo">
                <option value="">-- Sin trabajo asignado --</option>

                <!-- ===== BLOQUE 5 — Una opción por cada trabajo ===== -->
                <?php while ($trabajo = mysqli_fetch_assoc($resultado)) { ?>
                    <option value="<?php echo $trabajo["id_trabajo"]; ?>">
                        <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                    </option>
                <?php } ?>
            </select>
        </p>

        <p><input type="submit" value="Guardar"></p>

    </form>

    <?php
    // ===== BLOQUE 6 — Cerrar la conexión =====
    mysqli_close($conexion);
    ?>

</body>
</html>
