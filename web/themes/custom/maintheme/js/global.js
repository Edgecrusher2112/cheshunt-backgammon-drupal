const updateThemeToggle = () => {
  const toggle = document.getElementById('theme-toggle');

  if (!toggle) {
    return;
  }

  const theme = document.documentElement.getAttribute('data-bs-theme') || 'light';
  const label = theme === 'dark'
    ? 'Switch to light mode'
    : 'Switch to dark mode';

  toggle.setAttribute('aria-label', label);
  toggle.setAttribute('title', label);
};

document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
  const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

  updateThemeToggle();
});

document.addEventListener('click', (event) => {
  const toggle = event.target.closest('#theme-toggle');

  if (!toggle) {
    return;
  }

  const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

  document.documentElement.setAttribute('data-bs-theme', newTheme);
  updateThemeToggle();

  try {
    localStorage.setItem('theme', newTheme);
  }
  catch (error) {
    // The mode still changes when storage is unavailable.
  }
});
