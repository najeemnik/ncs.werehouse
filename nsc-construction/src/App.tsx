import React from 'react';
import {AppProvider} from './context/AppContext';
import {Workspace} from './modern/Workspace';
export default function App(){return <AppProvider><Workspace/></AppProvider>;}
