
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
