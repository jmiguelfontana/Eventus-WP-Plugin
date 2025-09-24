echo '<script>
(function(){
    document.addEventListener("click", function(e){
        var cell = e.target.closest("td.dt-control");
        if (!cell) return;

        var row = cell.parentElement;
        var details = row.nextElementSibling;
        if (!details || !details.classList.contains("ba-device-details")) return;

        var isHidden = details.style.display === "none" || details.style.display === "";
        details.style.display = isHidden ? "table-row" : "none";

        row.classList.toggle("is-expanded", isHidden);
        row.classList.toggle("dt-hasChild", isHidden);
        row.classList.toggle("shown", isHidden);

        cell.setAttribute("aria-expanded", isHidden ? "true" : "false");

        // 🔹 Solo cargar AJAX la primera vez que se abre
        if (isHidden && !details.dataset.loaded) {
            var deviceId = row.getAttribute("data-device-id");
            if (deviceId) {
                var td = details.querySelector("td");
                td.innerHTML = "<em>Cargando detalles...</em>";

                fetch(ajaxurl + "?action=ba_load_device_details&id=" + encodeURIComponent(deviceId))
                    .then(function(res){ return res.json(); })
                    .then(function(json){
                        if (json.success && json.data.html) {
                            td.innerHTML = json.data.html;
                            details.dataset.loaded = "1"; // marcar como cargado
                        } else {
                            td.innerHTML = "<em>No se pudieron cargar los detalles.</em>";
                        }
                    })
                    .catch(function(){
                        td.innerHTML = "<em>Error al cargar detalles.</em>";
                    });
            }
        }
    });
})();
</script>';
