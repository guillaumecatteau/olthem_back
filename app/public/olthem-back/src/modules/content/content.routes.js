import { Router } from 'express';
import { defaultSections } from '../../config/site-sections.js';

export const contentRouter = Router();

contentRouter.get('/sections', (req, res) => {
  res.json(defaultSections);
});
