import { app } from './app.js';
import { env } from './config/env.js';

app.listen(env.port, () => {
  console.log(`[olthem-back] API running on http://localhost:${env.port}`);
});
