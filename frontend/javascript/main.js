function toggleSource(value) {
            var newDiv = document.getElementById('source-new');
            var existDiv = document.getElementById('source-existing');
            var fileInput = document.getElementById('presentation');

            if (value === 'existing' && existDiv) {
                newDiv.style.display = 'none';
                existDiv.style.display = 'block';
                if (fileInput) fileInput.removeAttribute('required');
            } else {
                newDiv.style.display = 'block';
                if (existDiv) existDiv.style.display = 'none';
            }
        }