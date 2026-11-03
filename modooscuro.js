document.addEventListener("DOMContentLoaded", () => {

    const botonTema = document.getElementById("btn-tema");

    // Recuperar el tema guardado
    const temaGuardado = localStorage.getItem("tema");

    if (temaGuardado === "oscuro") {
        document.body.classList.add("modo-oscuro");
        botonTema.textContent = "☀️ Modo claro";
    }

    // Cambiar el tema al presionar el botón
    botonTema.addEventListener("click", () => {

        document.body.classList.toggle("modo-oscuro");

        if (document.body.classList.contains("modo-oscuro")) {
            botonTema.textContent = "☀️ Modo claro";
            localStorage.setItem("tema", "oscuro");
        } else {
            botonTema.textContent = "🌙 Modo oscuro";
            localStorage.setItem("tema", "claro");
        }

    });

});