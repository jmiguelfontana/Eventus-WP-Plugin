import os

# Ruta base del plugin (ajusta si es necesario)
# PLUGIN_DIR = os.path.join(os.getcwd(), "includes")
PLUGIN_DIR = r"D:\Projects\docker\uditrace\volume\wp-content\plugins\eventusapi\includes"

def check_file(path):
    with open(path, "rb") as f:
        content = f.read()

    # Detectar BOM en cualquier caso
    if content.startswith(b"\xef\xbb\xbf"):
        print(f"❌ BOM detectado en: {path}")

    # Convertir a texto para comprobar espacios antes de <?php
    try:
        text = content.decode("utf-8", errors="ignore")
    except Exception as e:
        print(f"⚠️ No se pudo decodificar {path}: {e}")
        return

    # Detectar si empieza con espacios o saltos de línea antes de <?php
    stripped = text.lstrip("\r\n\t ")
    if stripped.startswith("<?php") and text != stripped:
        print(f"❌ Espacios o saltos antes de <?php en: {path}")

def main():
    found = False
    for root, _, files in os.walk(PLUGIN_DIR):
        for filename in files:
            if filename.endswith(".php"):
                check_file(os.path.join(root, filename))
                found = True
    if not found:
        print("⚠️ No se encontraron archivos PHP en", PLUGIN_DIR)
    else:
        print("✅ Revisión terminada")

if __name__ == "__main__":
    main()
