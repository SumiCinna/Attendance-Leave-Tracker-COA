const toggleButtons = document.querySelectorAll(".toggle-password");

toggleButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
        event.preventDefault();
        const targetId = button.getAttribute("data-target");
        const input = document.getElementById(targetId);
        if (!input) return;
        const isPassword = input.getAttribute("type") === "password";
        input.setAttribute("type", isPassword ? "text" : "password");
        
        const eyeOpen = button.querySelector(".eye-open");
        const eyeClosed = button.querySelector(".eye-closed");
        if (eyeOpen && eyeClosed) {
            if (isPassword) {
                eyeOpen.style.display = "none";
                eyeClosed.style.display = "block";
            } else {
                eyeOpen.style.display = "block";
                eyeClosed.style.display = "none";
            }
        }
    });
});

