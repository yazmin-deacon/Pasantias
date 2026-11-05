document.addEventListener("DOMContentLoaded", () => {

    const botonTema = document.getElementById("btn-tema");

    if (!botonTema) return;

    const temaGuardado = localStorage.getItem("tema");

    if (temaGuardado === "oscuro") {
        document.body.classList.add("modo-oscuro");
        botonTema.textContent = "☀ Modo claro";
    } else {
        botonTema.textContent = "☽ Modo oscuro";
    }

    botonTema.addEventListener("click", () => {

        document.body.classList.toggle("modo-oscuro");

        if (document.body.classList.contains("modo-oscuro")) {
            botonTema.textContent = "☀ Modo claro";
            localStorage.setItem("tema", "oscuro");
        } else {
            botonTema.textContent = "☽ Modo oscuro";
            localStorage.setItem("tema", "claro");
        }

    });

});