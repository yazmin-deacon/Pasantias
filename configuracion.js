document.addEventListener("DOMContentLoaded", () => {

    const boton = document.getElementById("btn-configuracion");
    const menu = document.getElementById("menu-configuracion");

    if (!boton || !menu) {
        console.error("No se encontró el botón o el menú de configuración.");
        return;
    }

    boton.addEventListener("click", (evento) => {

        evento.stopPropagation();

        menu.hidden = !menu.hidden;

        boton.setAttribute(
            "aria-expanded",
            String(!menu.hidden)
        );

    });

    menu.addEventListener("click", (evento) => {
        evento.stopPropagation();
    });

    document.addEventListener("click", () => {
        menu.hidden = true;
        boton.setAttribute("aria-expanded", "false");
    });

    document.addEventListener("keydown", (evento) => {

        if (evento.key === "Escape") {
            menu.hidden = true;
            boton.setAttribute("aria-expanded", "false");
        }

    });

});