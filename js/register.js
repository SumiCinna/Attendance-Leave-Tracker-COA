const form = document.getElementById("registerForm");
const submitBtn = document.getElementById("registerSubmit");

const fields = {
    firstName: document.getElementById("first_name"),
    middleName: document.getElementById("middle_name"),
    lastName: document.getElementById("last_name"),
    email: document.getElementById("email"),
    password: document.getElementById("password")
};

const namePattern = /^[a-zA-Z\s\-']+$/;

const hints = {
    firstName: document.getElementById("firstNameHint"),
    middleName: document.getElementById("middleNameHint"),
    lastName: document.getElementById("lastNameHint"),
    email: document.getElementById("emailHint"),
    passLength: document.getElementById("passLength"),
    passUpper: document.getElementById("passUpper"),
    passLower: document.getElementById("passLower"),
    passNumber: document.getElementById("passNumber")
};

function setHint(element, valid) {
    if (!element) return;
    element.classList.toggle("valid", valid);
}

function validateName(value) {
    return value.length > 0 && value.length <= 100 && namePattern.test(value);
}

function validateEmail(value) {
    return value.length > 0 && /@gmail\.com$/.test(value) && /^[^\s@]+@gmail\.com$/.test(value);
}

function validatePassword(value) {
    return {
        length: value.length >= 8 && value.length <= 30,
        upper: /[A-Z]/.test(value),
        lower: /[a-z]/.test(value),
        number: /[0-9]/.test(value)
    };
}

function updateState() {
    const firstValid = validateName(fields.firstName.value.trim());
    const middleValid = validateName(fields.middleName.value.trim());
    const lastValid = validateName(fields.lastName.value.trim());
    const emailValid = validateEmail(fields.email.value.trim());
    const passState = validatePassword(fields.password.value);

    setHint(hints.firstName, firstValid);
    setHint(hints.middleName, middleValid);
    setHint(hints.lastName, lastValid);
    setHint(hints.email, emailValid);
    setHint(hints.passLength, passState.length);
    setHint(hints.passUpper, passState.upper);
    setHint(hints.passLower, passState.lower);
    setHint(hints.passNumber, passState.number);

    const formValid = firstValid && middleValid && lastValid && emailValid && passState.length && passState.upper && passState.lower && passState.number;
    submitBtn.disabled = !formValid;
}

Object.values(fields).forEach((field) => {
    field.addEventListener("input", updateState);
});

form.addEventListener("submit", (event) => {
    updateState();
    if (submitBtn.disabled) {
        event.preventDefault();
    }
});

updateState();

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

