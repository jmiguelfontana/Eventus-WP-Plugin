import os

PLUGIN_DIR = r"D:\Projects\docker\uditrace\volume\wp-content\plugins\eventusapi\includes"

def remove_bom(path):
    with open(path, "rb") as f:
        content = f.read()
    if content.startswith(b"\xef\xbb\xbf"):
        print(f"🔧 Corrigiendo BOM en: {path}")
        content = content[3:]  # quitar los 3 bytes del BOM
        with open(path, "wb") as f:
            f.write(content)

def main():
    for root, _, files in os.walk(PLUGIN_DIR):
        for filename in files:
            if filename.endswith(".php"):
                remove_bom(os.path.join(root, filename))
    print("✅ Reparación terminada")

if __name__ == "__main__":
    main()
