
document.addEventListener("DOMContentLoaded", () => {

    // Animación de entrada de las secciones
    const elementos = document.querySelectorAll(
        "header, .tarjeta, aside, footer"
    );

    elementos.forEach(elemento => {
        elemento.classList.add("animar-entrada");
    });

    // Detectar cuando los elementos aparecen en pantalla
    const observador = new IntersectionObserver((entradas) => {

        entradas.forEach(entrada => {

            if (entrada.isIntersecting) {
                entrada.target.classList.add("visible");
                observador.unobserve(entrada.target);
            }

        });

    }, {
        threshold: 0.15
    });

    elementos.forEach(elemento => {
        observador.observe(elemento);
    });


    // Animar los pacientes y turnos que se agregan dinámicamente
    function animarElementos(lista) {

        if (!lista) return;

        const elementos = lista.querySelectorAll("li");

        elementos.forEach(elemento => {
            elemento.classList.remove("lista-animada");

            // Reiniciar la animación
            void elemento.offsetWidth;

            elemento.classList.add("lista-animada");
        });

    }


    // Observar cambios en la lista de pacientes
    const listaPacientes = document.getElementById(
        "lista-pacientes-db"
    );

    if (listaPacientes) {

        const observadorPacientes = new MutationObserver(() => {
            animarElementos(listaPacientes);
        });

        observadorPacientes.observe(listaPacientes, {
            childList: true
        });

    }


    // Observar cambios en la lista de turnos
    const listaTurnos = document.getElementById(
        "lista-turnos-db"
    );

    if (listaTurnos) {

        const observadorTurnos = new MutationObserver(() => {
            animarElementos(listaTurnos);
        });

        observadorTurnos.observe(listaTurnos, {
            childList: true
        });

    }


    // Botón para desplazarse hacia abajo
    const botonScroll = document.querySelector(".btn-scroll-down");

    if (botonScroll) {

        botonScroll.addEventListener("click", () => {

            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: "smooth"
            });

        });

    }

});

/* =====================================
   ANIMACIONES - BOTONES DE PACIENTES
   RESPETANDO LOS ESTILOS ORIGINALES
===================================== */

document.addEventListener("DOMContentLoaded", () => {

    const botones = document.querySelectorAll(
        ".pagina-listado .btn-nuevo-paciente, " +
        ".pagina-listado .accion-tabla"
    );

    botones.forEach(boton => {

        // Al pasar el mouse
        boton.addEventListener("mouseenter", () => {
            boton.style.transform = "translateY(-3px)";
            boton.style.boxShadow =
                "0 6px 15px rgba(20, 120, 135, 0.25)";
        });

        // Al retirar el mouse
        boton.addEventListener("mouseleave", () => {
            boton.style.transform = "";
            boton.style.boxShadow = "";
        });

        // Al presionar
        boton.addEventListener("mousedown", () => {
            boton.style.transform = "scale(0.97)";
        });

        // Al soltar
        boton.addEventListener("mouseup", () => {
            boton.style.transform = "";
        });

        // Al perder el foco
        boton.addEventListener("blur", () => {
            boton.style.transform = "";
            boton.style.boxShadow = "";
        });

    });

});
