<?php
// The application is served from public/. If the project folder itself is
// opened in the browser, send the visitor there.
header('Location: public/', true, 302);
exit();
