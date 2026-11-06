import { getAccessToken } from './googleDriveAuth';

export interface GoogleDriveFile {
  id: string;
  name: string;
  mimeType: string;
  webViewLink?: string;
  webContentLink?: string;
  createdTime?: string;
  size?: string;
  iconLink?: string;
  parents?: string[];
}

/**
 * Ensures or creates a parent folder in Google Drive for the app and project
 */
export async function getOrCreateFolder(folderName: string, parentFolderId?: string): Promise<string> {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated with Google Drive. Please sign in with Google first.');

  // Search if folder already exists
  let query = `mimeType = 'application/vnd.google-apps.folder' and name = '${folderName.replace(/'/g, "\\'")}' and trashed = false`;
  if (parentFolderId) {
    query += ` and '${parentFolderId}' in parents`;
  }

  const searchRes = await fetch(`https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(query)}&fields=files(id,name)&spaces=drive`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!searchRes.ok) {
    const err = await searchRes.text();
    throw new Error(`Failed to query Google Drive: ${err}`);
  }

  const searchData = await searchRes.json();
  if (searchData.files && searchData.files.length > 0) {
    return searchData.files[0].id;
  }

  // Create folder
  const folderMetadata: Record<string, any> = {
    name: folderName,
    mimeType: 'application/vnd.google-apps.folder',
  };
  if (parentFolderId) {
    folderMetadata.parents = [parentFolderId];
  }

  const createRes = await fetch('https://www.googleapis.com/drive/v3/files?fields=id,name', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(folderMetadata),
  });

  if (!createRes.ok) {
    const err = await createRes.text();
    throw new Error(`Failed to create Google Drive folder: ${err}`);
  }

  const newFolder = await createRes.json();
  return newFolder.id;
}

/**
 * Upload a file (Blob, text, or Data URL) directly to Google Drive
 */
export async function uploadFileToGoogleDrive({
  fileName,
  mimeType,
  content,
  folderId,
}: {
  fileName: string;
  mimeType: string;
  content: Blob | string;
  folderId?: string;
}): Promise<GoogleDriveFile> {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated with Google Drive. Please sign in with Google first.');

  let blobContent: Blob;
  if (typeof content === 'string') {
    if (content.startsWith('data:')) {
      // Data URL
      const arr = content.split(',');
      const match = arr[0].match(/:(.*?);/);
      const fileMime = match ? match[1] : mimeType;
      const bstr = atob(arr[1]);
      let n = bstr.length;
      const u8arr = new Uint8Array(n);
      while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
      }
      blobContent = new Blob([u8arr], { type: fileMime });
    } else {
      blobContent = new Blob([content], { type: mimeType });
    }
  } else {
    blobContent = content;
  }

  const metadata: Record<string, any> = {
    name: fileName,
    mimeType,
  };
  if (folderId) {
    metadata.parents = [folderId];
  }

  const form = new FormData();
  form.append(
    'metadata',
    new Blob([JSON.stringify(metadata)], { type: 'application/json' })
  );
  form.append('file', blobContent);

  const res = await fetch(
    'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,mimeType,webViewLink,webContentLink,createdTime,size',
    {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: form,
    }
  );

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Google Drive upload failed: ${err}`);
  }

  const fileData = await res.json();
  return fileData as GoogleDriveFile;
}

/**
 * List files in a Google Drive folder or created by app
 */
export async function listGoogleDriveFiles(folderId?: string): Promise<GoogleDriveFile[]> {
  const token = await getAccessToken();
  if (!token) throw new Error('Not authenticated with Google Drive.');

  let query = 'trashed = false';
  if (folderId) {
    query += ` and '${folderId}' in parents`;
  }

  const res = await fetch(
    `https://www.googleapis.com/drive/v3/files?q=${encodeURIComponent(
      query
    )}&fields=files(id,name,mimeType,webViewLink,webContentLink,createdTime,size,iconLink)&orderBy=createdTime desc&pageSize=50`,
    {
      headers: { Authorization: `Bearer ${token}` },
    }
  );

  if (!res.ok) {
    const err = await res.text();
    throw new Error(`Failed to list Google Drive files: ${err}`);
  }

  const data = await res.json();
  return (data.files || []) as GoogleDriveFile[];
}


async function scopedDriveFile(fileId:string,folderId:string){
 if(!/^[a-zA-Z0-9_-]{1,200}$/.test(fileId)||!/^[a-zA-Z0-9_-]{1,200}$/.test(folderId))throw Error('Invalid Drive scope');const token=await getAccessToken();if(!token)throw Error('Google sign-in required');
 const response=await fetch('https://www.googleapis.com/drive/v3/files/'+encodeURIComponent(fileId)+'?fields=id,mimeType,parents,size',{headers:{Authorization:'Bearer '+token}});if(!response.ok)throw Error('File unavailable');const file=await response.json();if(!file.parents?.includes(folderId)||file.mimeType!=='application/json'||Number(file.size)>10485760)throw Error('Backup is outside this project or exceeds 10 MB');return token;
}
export async function downloadDriveBackup(fileId:string,folderId:string){const token=await scopedDriveFile(fileId,folderId),r=await fetch('https://www.googleapis.com/drive/v3/files/'+encodeURIComponent(fileId)+'?alt=media',{headers:{Authorization:'Bearer '+token}});if(!r.ok||!r.body)throw Error('Backup download failed');const reader=r.body.getReader(),chunks:Uint8Array[]= [];let size=0;for(;;){const {value,done}=await reader.read();if(done)break;size+=value.byteLength;if(size>10485760){await reader.cancel();throw Error('Backup exceeds 10 MB');}chunks.push(value);}const text=await new Blob(chunks as BlobPart[]).text();JSON.parse(text);return text;}
export async function trashGoogleDriveFile(fileId:string,folderId:string){const token=await scopedDriveFile(fileId,folderId),r=await fetch('https://www.googleapis.com/drive/v3/files/'+encodeURIComponent(fileId),{method:'PATCH',headers:{Authorization:'Bearer '+token,'Content-Type':'application/json'},body:JSON.stringify({trashed:true})});if(!r.ok)throw Error('Could not move backup to trash');}
