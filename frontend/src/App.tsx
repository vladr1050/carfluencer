import { RouterProvider } from 'react-router-dom';
import { ThemeProvider } from 'next-themes';
import { AuthProvider } from './auth/AuthContext';
import { router } from './app/routes';

export default function App(): JSX.Element {
  return (
    <ThemeProvider attribute="class" defaultTheme="dark" enableSystem>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </ThemeProvider>
  );
}
