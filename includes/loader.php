<div id="loader" style="
  position: fixed;
  top: 0; left: 0; width: 100%; height: 100%;
  background: #0e0e0e;
  display: flex; align-items: center; justify-content: center;
  color: #2b8ffb; font-family: 'Segoe UI', sans-serif;
  font-size: 1.2em; z-index: 9999;
  transition: opacity 0.5s;
">
  <div>
    <div style="border: 3px solid #2b8ffb33; border-top: 3px solid #2b8ffb;
                border-radius: 50%; width: 40px; height: 40px;
                margin: 0 auto; animation: spin 1s linear infinite;"></div>
    <p>Hungary Life UCP betöltés...</p>
  </div>
</div>

<style>
@keyframes spin { from {transform: rotate(0deg);} to {transform: rotate(360deg);} }
</style>
