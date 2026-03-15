<html>

<head>
    <title>New Entry</title>
</head>

<body>

    <section class="entry-section">
        <h2>New Entry</h2>
        <div class="card">
            <form action="add.php" method="post" class="entry-form grid-form">
                <div class="form-group">
                    <label for="brand">Brand</label>
                    <select id="brand" name="brand" required>
                        <option value=""></option>
                        <option value="HP">HP</option>
                        <option value="DELL">DELL</option>
                        <option value="Lenovo">Lenovo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="model">Model</label>
                    <input type="text" id="model" name="model" required>
                </div>
                <div class="form-group">
                    <label for="battery">Battery</label>
                    <input type="checkbox" id="battery" name="battery" value="Yes" required>
                </div>
                <div class="form-group">
                    <label for="special_features">Features</label>
                    <input type="text" id="special_features" name="special_features">
                </div>

                <div class="form-group">
                    <label for="enableRam"> <input type="checkbox" id="enableRam"> RAM </label>
                    <select id="ram" name="ram" disabled>
                        <option value="4GB">4 GB</option>
                        <option value="8GB">8 GB</option>
                        <option value="16GB">16 GB</option>
                        <option value="32GB">32 GB</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="enableStorage"> <input type="checkbox" id="enableStorage"> Storage </label>
                </div>
                <div class="form-group"> <input type="text" id="storage" name="storage" disabled>
                </div>

                <script>
                const ramCheckbox = document.getElementById('enableRam');
                const ramInput = document.getElementById('ram');
                const storageCheckbox = document.getElementById('enableStorage');
                const storageInput = document.getElementById('storage');
                ramCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        ramInput.disabled = false;
                        ramInput.required = true;
                    } else {
                        ramInput.disabled = true;
                        ramInput.required = false;
                        ramInput.value = "";
                    }
                });
                storageCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        storageInput.disabled = false;
                        storageInput.required = true;
                    } else {
                        storageInput.disabled = true;
                        storageInput.required = false;
                        storageInput.value = "";
                    }
                });
                </script>
                <div class="form-group">
                    <label for="cpu_type">CPU Type</label>
                    <input type="text" id="cpu_type" name="cpu_type" required>
                </div>
                <div class="form-group">
                    <label for="cpu_speed">CPU Speed</label>
                    <input type="text" id="cpu_speed" name="cpu_speed">
                </div>
                <div class="form-group">
                    <label for="cpu_cores">CPU Cores</label>
                    <input type="text" id="cpu_cores" name="cpu_cores">
                </div>
                <div class="form-group">
                    <label for="bios_state">BIOS State</label>
                    <select id="bios_state" name="bios_state">
                        <option value=""></option>
                        <option value="Unlocked">Unlocked</option>
                        <option value="Locked">Locked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="os">OS</label>
                    <input type="text" id="os" name="os">
                </div>
                <div class="form-group full-width">
                    <input type="submit" value="Add Laptop" class="btn-success">
                </div>
            </form>
        </div>
    </section>
</body>

</html>