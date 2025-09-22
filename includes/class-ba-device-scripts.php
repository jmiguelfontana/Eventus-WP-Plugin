<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Scripts {
    private static $printed = false;

    /**
     * Imprime el script de interacción (toggle de detalles) una sola vez.
     */
    public static function maybe_output_script() {
        if (self::$printed) {
            return;
        }
        self::$printed = true;

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
            });
        })();
        </script>';
    }
}