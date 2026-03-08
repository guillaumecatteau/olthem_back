import 'dotenv/config';

function required(name, fallback) {
  const value = process.env[name] ?? fallback;
  if (!value) {
    throw new Error(`Missing required env var: ${name}`);
  }
  return value;
}

export const env = {
  nodeEnv: process.env.NODE_ENV || 'development',
  port: Number(process.env.PORT || 4000),
  databaseUrl: required('DATABASE_URL'),
  frontendOrigin: required('FRONTEND_ORIGIN', '*'),
  jwtSecret: required('JWT_SECRET'),
  jwtExpiresIn: process.env.JWT_EXPIRES_IN || '7d',
  adminSeedEmail: process.env.ADMIN_SEED_EMAIL || 'admin@olthem.local',
  adminSeedPassword: process.env.ADMIN_SEED_PASSWORD || 'ChangeMe123!',
};
