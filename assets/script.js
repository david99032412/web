// Betöltő animáció
window.addEventListener("load", () => {
  const loader = document.getElementById("loader");
  if (loader) {
    loader.style.opacity = 0;
    setTimeout(() => loader.remove(), 500);
  }
});

// Navigációs link aktív állapot
document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".main-nav a");
  links.forEach(link => {
    if (link.href === window.location.href) {
      link.classList.add("active");
    }
  });
});
