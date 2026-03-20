import { RouterProvider } from 'react-router';
import { ThemeProvider } from 'next-themes';
import { AuthProvider } from '@/auth/AuthContext';
import { router } from './routes';

function App() {
  return (
    <ThemeProvider attribute="class" defaultTheme="dark" enableSystem>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;
