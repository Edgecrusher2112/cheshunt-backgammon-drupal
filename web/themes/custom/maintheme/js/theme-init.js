(() => {
  let theme = 'light';

  try {
    const storedTheme = localStorage.getItem('theme');
    if (storedTheme === 'light' || storedTheme === 'dark') {
      theme = storedTheme;
    }
  }
  catch (error) {
    // Storage can be unavailable in privacy-restricted browser contexts.
  }

  document.documentElement.setAttribute('data-bs-theme', theme);
})();
