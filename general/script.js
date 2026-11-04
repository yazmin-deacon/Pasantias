document.addEventListener("DOMContentLoaded", () => {
    // Definimos el rol actual (esto luego vendría del inicio de sesión)
    // Puede ser: "secretaria" o "doctora"
    const rolUsuario = "doctora"; 

    // Inicializar listeners y cargas iniciales
    configurarPermisosPorRol(rolUsuario);
    cargarPacientes();
    cargarProximosTurnos();

    // Eventos de Formularios
    document.getElementById("form-paciente").addEventListener("submit", guardarPaciente);
    document.getElementById("form-estudio-manual").addEventListener("submit", guardarEstudioManual);
    document.getElementById("form-completar-estudio").addEventListener("submit", completarEstudio);
    document.getElementById("btn-buscar").addEventListener("click", buscarPaciente);
});

// 1. Control de Permisos y Usuarios (Secretaria / Doctora)
function configurarPermisosPorRol(rol) {
    const bloqueEstudioManual = document.querySelector(".bloque-estudio-manual");

    if (rol === "secretaria") {
        // La secretaria solo puede consultar y cargar pacientes básicos
        if (bloqueEstudioManual) {
            bloqueEstudioManual.style.display = "none"; // Ocultar carga de estudios manuales
        }
    } else if (rol === "doctora") {
        // La doctora tiene acceso completo
        if (bloqueEstudioManual) {
            bloqueEstudioManual.style.display = "block";
        }
    }
}

// 2. Cargar lista general de pacientes (GET)
async function cargarPacientes() {
    try {
        const response = await fetch("/api/pacientes");
        const pacientes = await response.json();
        
        const ul = document.getElementById("lista-pacientes-db");
        ul.innerHTML = ""; // Limpiar lista

        pacientes.forEach(p => {
            const li = document.createElement("li");
            li.innerHTML = `
                <span>👤</span> <strong>${p.nombre} ${p.apellido}</strong>
                <p>DNI: ${p.dni} | Empresa: ${p.empresa || 'N/A'}</p>
            `;
            ul.appendChild(li);
        });
    } catch (error) {
        console.error("Error al obtener los pacientes:", error);
    }
}

// 3. Agregar Paciente con Exposiciones (POST)
async function guardarPaciente(e) {
    e.preventDefault();

    // Obtener las exposiciones seleccionadas en el <select multiple>
    const selectExposiciones = document.getElementById("exposiciones");
    const exposicionesSeleccionadas = Array.from(selectExposiciones.selectedOptions).map(opt => opt.value);

    const nuevoPaciente = {
        nombre: document.getElementById("nombre").value,
        apellido: document.getElementById("apellido").value,
        dni: document.getElementById("dni").value,
        telefono: document.getElementById("telefono").value,
        empresa: document.getElementById("empresa").value,
        exposiciones: exposicionesSeleccionadas // Al enviar esto, el Backend generará los estudios automáticos
    };

    try {
        const response = await fetch("/api/pacientes", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(nuevoPaciente)
        });

        if (response.ok) {
            alert("Paciente registrado correctamente. Los estudios automáticos fueron asignados.");
            document.getElementById("form-paciente").reset();
            cargarPacientes(); // Recargar la lista
            cargarProximosTurnos(); // Actualizar la lista de estudios/turnos
        } else {
            const errData = await response.json();
            alert("Error: " + (errData.mensaje || "No se pudo registrar el paciente (¿DNI duplicado?)"));
        }
    } catch (error) {
        console.error("Error al guardar paciente:", error);
    }
}

// 4. Agregar Estudio Manual (Exclusivo Doctora)
async function guardarEstudioManual(e) {
    e.preventDefault();

    const nuevoEstudio = {
        dniPaciente: document.getElementById("dni-paciente-estudio").value,
        nombreEstudio: document.getElementById("nombre-estudio").value,
        tipo: "manual"
    };

    try {
        const response = await fetch("/api/estudios/manual", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(nuevoEstudio)
        });

        if (response.ok) {
            alert("Estudio manual agregado con éxito.");
            document.getElementById("form-estudio-manual").reset();
            cargarProximosTurnos();
        } else {
            alert("Error al agregar el estudio manual.");
        }
    } catch (error) {
        console.error("Error al guardar estudio manual:", error);
    }
}

// 5. Marcar Estudio como Realizado y Subir Archivo PDF/Imagen
async function completarEstudio(e) {
    e.preventDefault();

    // Para enviar archivos adjuntos se debe utilizar FormData
    const formData = new FormData();
    formData.append("idEstudio", document.getElementById("id-estudio").value);
    formData.append("fechaRealizado", document.getElementById("fecha-realizado").value);
    formData.append("observaciones", document.getElementById("observaciones").value);
    formData.append("archivo", document.getElementById("archivo-estudio").files[0]);

    try {
        const response = await fetch("/api/estudios/completar", {
            method: "PUT",
            body: formData // No se pone Content-Type header cuando se usa FormData
        });

        if (response.ok) {
            alert("Estudio completado y archivo subido correctamente.");
            document.getElementById("form-completar-estudio").reset();
            cargarProximosTurnos();
        } else {
            alert("Error al guardar el resultado del estudio.");
        }
    } catch (error) {
        console.error("Error al completar el estudio:", error);
    }
}

// 6. Buscar Paciente por DNI o Apellido
async function buscarPaciente() {
    const criterio = document.getElementById("buscar-paciente").value;
    if (!criterio) return cargarPacientes();

    try {
        const response = await fetch(`/api/pacientes/buscar?q=${encodeURIComponent(criterio)}`);
        const resultados = await response.json();

        const ul = document.getElementById("lista-pacientes-db");
        ul.innerHTML = "";

        if (resultados.length === 0) {
            ul.innerHTML = "<li>No se encontraron pacientes.</li>";
            return;
        }

        resultados.forEach(p => {
            const li = document.createElement("li");
            li.innerHTML = `
                <span>👤</span> <strong>${p.nombre} ${p.apellido}</strong>
                <p>DNI: ${p.dni} | Empresa: ${p.empresa || 'N/A'}</p>
            `;
            ul.appendChild(li);
        });
    } catch (error) {
        console.error("Error en la búsqueda:", error);
    }
}

// 7. Cargar lista de turnos / estudios pendientes del Aside
async function cargarProximosTurnos() {
    try {
        const response = await fetch("/api/estudios/pendientes");
        const turnos = await response.json();

        const ul = document.getElementById("lista-turnos-db");
        ul.innerHTML = "";

        turnos.forEach(t => {
            const li = document.createElement("li");
            
            // Construir enlace de archivo si ya está realizado
            let estadoHTML = `<span>[ ${t.estado} (${t.origen}) ]</span>`;
            if (t.estado === "Realizado" && t.archivoUrl) {
                estadoHTML = `<a href="${t.archivoUrl}" target="_blank">[ Ver PDF/Imagen ]</a>`;
            }

            li.innerHTML = `
                <time>${t.hora}</time> <strong>${t.nombrePaciente}</strong> - ${t.nombreEstudio}
                ${estadoHTML}
            `;
            ul.appendChild(li);
        });
    } catch (error) {
        console.error("Error al cargar próximos turnos:", error);
    }
}