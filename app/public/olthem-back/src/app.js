import express from 'express';
import helmet from 'helmet';
import morgan from 'morgan';
import cors from 'cors';
import cookieParser from 'cookie-parser';
import { env } from './config/env.js';
import { authRouter } from './modules/auth/auth.routes.js';
import { contentRouter } from './modules/content/content.routes.js';
import { postsRouter, adminPostsRouter } from './modules/posts/posts.routes.js';
import { ateliersRouter, adminAteliersRouter } from './modules/ateliers/ateliers.routes.js';
import { usersRouter } from './modules/users/users.routes.js';
import { errorHandler, notFoundHandler } from './middlewares/error-handler.js';

export const app = express();

app.use(helmet());
app.use(
  cors({
    origin: env.frontendOrigin === '*' ? true : env.frontendOrigin,
    credentials: true,
  })
);
app.use(express.json());
app.use(cookieParser());
app.use(morgan('dev'));

app.get('/health', (req, res) => {
  res.json({ ok: true, service: 'olthem-back' });
});

app.use('/auth', authRouter);
app.use('/content', contentRouter);
app.use('/blog', postsRouter);
app.use('/ateliers', ateliersRouter);
app.use('/admin/posts', adminPostsRouter);
app.use('/admin/ateliers', adminAteliersRouter);
app.use('/admin/utilisateurs', usersRouter);

app.use(notFoundHandler);
app.use(errorHandler);
